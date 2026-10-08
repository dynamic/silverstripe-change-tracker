<?php

namespace Symbiote\DataChange\Tests\Characterization;

use ReflectionProperty;
use SilverStripe\Core\Config\Config;
use SilverStripe\Security\Security;
use SilverStripe\Versioned\Versioned;
use Symbiote\DataChange\Extension\ChangeRecordable;
use Symbiote\DataChange\Model\DataChangeRecord;
use Symbiote\DataChange\Tests\Fixtures\PlainRecordable;
use Symbiote\DataChange\Tests\Fixtures\TrackedChild;
use Symbiote\DataChange\Tests\Fixtures\TrackedObject;
use Symbiote\DataChange\Tests\Fixtures\TrackedPage;

/**
 * What DataChangeRecord::track() and the extensions that call it store today
 */
class DataChangeRecordTrackTest extends CharacterizationTestCase
{
    public function testNewWritesNew()
    {
        $object = TrackedObject::create(['Title' => 'Fresh', 'Body' => 'Text']);
        $object->write();

        $records = $this->recordsFor($object);
        $this->assertCount(1, $records);
        $record = $records[0];
        $this->assertSame('New', $record->ChangeType);
        $this->assertSame('Stage.Stage', $record->Stage);
        $this->assertSame('Fresh', $record->ObjectTitle);

        $before = json_decode($record->Before, true);
        $after = json_decode($record->After, true);
        $this->assertNull($before['Title']);
        $this->assertSame(0, $before['ID']);
        $this->assertSame('Fresh', $after['Title']);
        $this->assertSame('Text', $after['Body']);
        $this->assertEquals($object->ID, $after['ID']);
        $this->assertSame(TrackedObject::class, $after['ClassName']);
    }

    public function testChangeStoresOnlyChangedFieldsJson()
    {
        $object = $this->makeObject('Before', ['Body' => 'unchanged']);

        $object->Title = 'After';
        $object->write();

        $records = $this->recordsFor($object);
        $this->assertSame(['New', 'Change'], $this->types($object));
        $this->assertSame('{"Title":"Before"}', $records[1]->Before);
        $this->assertSame('{"Title":"After"}', $records[1]->After);
        $this->assertSame('After', $records[1]->ObjectTitle);
    }

    public function testNoChangesNoRecord()
    {
        $object = $this->makeObject('Same');

        $object->write();
        $object->Title = 'Same';
        $object->write();

        $this->assertSame(['New'], $this->types($object));
    }

    public function testVersionOnlyChangeSkipped()
    {
        $page = $this->makePage('Versioned');

        $page->Version = 99;
        $page->write();
        $this->assertSame(['New'], $this->types($page));

        $this->resetTracking();
        $page->Version = 100;
        $page->Title = 'Versioned and titled';
        $page->write();

        $records = $this->recordsFor($page);
        $this->assertSame(['New', 'Change'], $this->types($page));
        $this->assertSame(['Version', 'Title'], array_keys(json_decode($records[1]->After, true)));
    }

    public function testSecurityIDIgnored()
    {
        $plain = $this->makePlain('Form data');

        $plain->Title = 'Form data edited';
        $plain->SecurityID = 'token';
        $plain->write();

        $records = $this->recordsFor($plain);
        $this->assertSame('{"Title":"Form data"}', $records[1]->Before);
        $this->assertSame('{"Title":"Form data edited"}', $records[1]->After);

        // a change to the token alone still counts as a change, and stores empty payloads
        $this->resetTracking();
        $plain->SecurityID = 'another token';
        $plain->write();

        $records = $this->recordsFor($plain);
        $this->assertCount(3, $records);
        $this->assertSame('Change', $records[2]->ChangeType);
        $this->assertSame('[]', $records[2]->Before);
        $this->assertSame('[]', $records[2]->After);
    }

    public function testFieldBlacklist()
    {
        $object = $this->makeObject('Secrets', ['Password' => 'first']);

        $object->Password = 'second';
        $object->Secret = 'visible';
        $object->write();

        $records = $this->recordsFor($object);
        $this->assertSame('{"Secret":null}', $records[1]->Before);
        $this->assertSame('{"Secret":"visible"}', $records[1]->After);

        $this->resetTracking();
        $object->Password = 'third';
        $object->write();
        $this->assertCount(2, $this->recordsFor($object), 'A change to a blacklisted field alone records nothing');

        $this->assertStringNotContainsString('Password', $records[0]->After);
        $this->assertStringNotContainsString('first', $records[0]->After);
    }

    public function testFieldBlacklistIsConfigurable()
    {
        Config::modify()->set(DataChangeRecord::class, 'field_blacklist', ['Password', 'Secret']);
        $object = $this->makeObject('Secrets');

        $object->Secret = 'hidden';
        $object->Title = 'Secrets edited';
        $object->write();

        $records = $this->recordsFor($object);
        $this->assertSame('{"Title":"Secrets"}', $records[1]->Before);
    }

    public function testIgnoredFieldsPerClass()
    {
        Config::modify()->set(
            ChangeRecordable::class,
            'ignored_fields',
            [PlainRecordable::class => ['Secret']]
        );
        $plain = $this->makePlain('Ignoring');

        $plain->Secret = 'not recorded';
        $plain->Notes = 'recorded';
        $plain->write();

        $records = $this->recordsFor($plain);
        $this->assertSame('{"Notes":null}', $records[1]->Before);
        $this->assertSame('{"Notes":"recorded"}', $records[1]->After);

        $this->resetTracking();
        $plain->Secret = 'still not recorded';
        $plain->write();
        $this->assertCount(2, $this->recordsFor($plain));
    }

    public function testDelete()
    {
        $plain = $this->makePlain('Doomed');
        $id = $plain->ID;
        $plain->delete();

        $records = DataChangeRecord::get()->filter('ChangeRecordID', $id)->sort('ID')->toArray();
        $this->assertSame(['New', 'Delete'], array_map(function ($r) {
            return $r->ChangeType;
        }, $records));
        $this->assertSame('Stage.Stage', $records[1]->Stage);
        $this->assertSame('null', $records[1]->Before);
        $this->assertSame('null', $records[1]->After);
        $this->assertSame('Doomed', $records[1]->ObjectTitle);
    }

    public function testDeleteFromLiveWhenReadingModeLive()
    {
        $plain = $this->makePlain('Doomed on live');

        Versioned::set_reading_mode('Stage.Live');
        $plain->delete();
        Versioned::set_reading_mode('Stage.Stage');

        $this->assertSame(['New', 'Delete from Live'], $this->types());
        $this->assertSame('Stage.Live', $this->lastRecord()->Stage);
    }

    public function testPublishStoresToMapInAfterNullBefore()
    {
        $object = $this->makeObject('To publish', ['Body' => 'content', 'Password' => 'kept in payload']);

        $object->publishRecursive();

        $this->assertSame(['New', 'Publish'], $this->types($object));
        $record = $this->lastRecord();
        $this->assertSame('null', $record->Before);
        $after = json_decode($record->After, true);
        $this->assertSame('To publish', $after['Title']);
        $this->assertSame('content', $after['Body']);
        $this->assertSame(TrackedObject::class, $after['ClassName']);
        $this->assertEquals($object->ID, $after['ID']);
        // the blacklist only filters change diffs, so the full record ends up in the log
        $this->assertSame('kept in payload', $after['Password']);
    }

    public function testUnpublishWritesDeleteFromLiveThenUnpublish()
    {
        $page = $this->makePage('Live then gone');
        $page->publishRecursive();
        $this->resetTracking();

        $page->doUnpublish();

        $this->assertSame(['New', 'Publish', 'Delete from Live', 'Unpublish'], $this->types($page));
        $records = $this->recordsFor($page);
        $this->assertSame('Stage.Live', $records[2]->Stage);
        $this->assertSame('null', $records[2]->Before);
        $this->assertSame('null', $records[2]->After);
        $this->assertSame('Stage.Stage', $records[3]->Stage);
        $this->assertSame('null', $records[3]->After);
        $before = json_decode($records[3]->Before, true);
        $this->assertSame('Live then gone', $before['Title']);
        $this->assertEquals($page->ID, $before['ID']);
    }

    public function testRollbackWritesPublishVersionToStage()
    {
        $page = $this->makePage('Rolled back');
        $page->publishRecursive();
        $this->resetTracking();
        $page->Title = 'Rolled back, edited';
        $page->write();
        $this->resetTracking();

        $page->doRevertToLive();
        $this->resetTracking();
        $page->rollbackSingle(1);

        $this->assertSame(
            ['New', 'Publish', 'Change', 'Publish Live to Stage', 'Publish 1 to Stage'],
            $this->types($page)
        );
        $records = $this->recordsFor($page);
        $this->assertSame('null', $records[3]->Before);
        $this->assertSame('null', $records[3]->After);
    }

    public function testEachTrackCreatesSeparateRow()
    {
        $object = $this->makeObject('Counted');

        $object->Title = 'Counted once';
        $object->write();
        $object->Title = 'Counted twice';
        $object->write();

        $records = $this->recordsFor($object);
        $this->assertCount(3, $records);
        $this->assertCount(3, array_unique(array_map(function ($r) {
            return $r->ID;
        }, $records)));
        $this->assertSame('{"Title":"Counted once"}', $records[2]->Before);
    }

    public function testServiceCacheKeyMismatchPinned()
    {
        $object = $this->makeObject('Cached');
        $object->Title = 'Cached again';
        $object->write();

        $cache = new ReflectionProperty($this->trackService(), 'dcr_cache');
        $cache->setAccessible(true);
        // The lookup key ends with the change type and the stored key does not, so the cache never hits
        $this->assertSame(
            ["{$object->ID}-" . TrackedObject::class],
            array_keys($cache->getValue($this->trackService()))
        );
    }

    public function testServiceDisabledWritesNothing()
    {
        $this->trackService()->disabled = true;

        $plain = $this->makePlain('Silent');
        $plain->Title = 'Still silent';
        $plain->write();

        $this->assertCount(0, $this->records());
        $this->assertNull($this->trackService()->track($plain, 'Change'));
    }

    public function testTrackReturnsRecordOrNull()
    {
        $plain = $this->makePlain('Direct');

        $this->assertNull(DataChangeRecord::create()->track($plain, 'Change'));

        $plain->Notes = 'changed';
        $record = DataChangeRecord::create()->track($plain, 'Change');
        $this->assertInstanceOf(DataChangeRecord::class, $record);
        $this->assertTrue($record->isInDB());

        $plain->write();
        $this->resetTracking();
        $forced = DataChangeRecord::create()->track($plain, 'Publish');
        $this->assertSame('Publish', $forced->ChangeType);
    }

    public function testSameRecordObjectMergeOrderIsInverted()
    {
        $plain = $this->makePlain('Merging');
        $record = DataChangeRecord::create();

        $plain->Notes = 'one';
        $record->track($plain, 'Change');
        $this->trackService()->disabled = true;
        $plain->write();
        $this->trackService()->disabled = false;

        $plain->Notes = 'two';
        $record->track($plain, 'Change');

        // The comments in track() promise the earliest before and the newest after. The array_replace() arguments
        // are the other way round, so the second call's before and the first call's after survive.
        $this->assertSame('{"Notes":"one"}', $record->Before);
        $this->assertSame('{"Notes":"one"}', $record->After);
        $this->assertSame(['New', 'Change'], $this->types($plain));
    }

    public function testMemberIdAndEmail()
    {
        $member = Security::getCurrentUser();
        $this->assertNotNull($member);

        $plain = $this->makePlain('Attributed');

        $record = $this->recordsFor($plain)[0];
        $this->assertEquals($member->ID, $record->ChangedByID);
        $this->assertSame($member->Email, $record->CurrentEmail);
        $this->assertNotEmpty($record->CurrentEmail);
    }

    public function testNoMemberStoresZeroWithoutWarning()
    {
        $this->logOut();

        $plain = PlainRecordable::create(['Title' => 'Anonymous']);
        $plain->write();

        $record = $this->lastRecord();
        $this->assertSame('New', $record->ChangeType);
        $this->assertEquals(0, $record->ChangedByID);
        $this->assertNull($record->CurrentEmail);
        $this->assertSame([], $this->describeWarnings($this->takeWarnings()));
    }

    public function testCurrentUrlFromServerVars()
    {
        $plain = $this->makePlain('Where');
        $this->assertSame(
            'http://tracker.test:8080/admin/pages/edit/EditForm/7/field/Pages?q=1',
            $this->recordsFor($plain)[0]->CurrentURL
        );

        $_SERVER['HTTPS'] = 'on';
        $_SERVER['SERVER_PORT'] = '8443';
        $_SERVER['REQUEST_URI'] = '/secure';
        $secure = $this->makePlain('Where secure');
        $this->assertSame('https://tracker.test:8443/secure', $this->recordsFor($secure)[0]->CurrentURL);

        unset($_SERVER['SERVER_PORT']);
        $_SERVER['HTTPS'] = 'off';
        $noPort = $this->makePlain('Where no port');
        $this->assertSame('http://tracker.test:80/secure', $this->recordsFor($noPort)[0]->CurrentURL);
    }

    public function testCurrentUrlCli()
    {
        unset($_SERVER['SERVER_NAME'], $_SERVER['REMOTE_ADDR']);

        $plain = $this->makePlain('From the command line');

        $record = $this->recordsFor($plain)[0];
        $this->assertSame('CLI', $record->CurrentURL);
        $this->assertSame('CLI', $record->RemoteIP);
    }

    public function testRemoteIpAgentReferer()
    {
        $plain = $this->makePlain('Request details');

        $record = $this->recordsFor($plain)[0];
        $this->assertSame('203.0.113.7', $record->RemoteIP);
        $this->assertSame('CharacterizationAgent/1.0', $record->Agent);
        $this->assertSame('http://referrer.test/previous', $record->Referer);

        unset($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_USER_AGENT']);
        $bare = $this->makePlain('No request details');
        $record = $this->recordsFor($bare)[0];
        $this->assertEmpty($record->Referer);
        $this->assertEmpty($record->Agent);
    }

    /**
     * @return array[] field name => [field name], so the data provider only returns strings
     */
    public function longValueProvider(): array
    {
        return [
            'ObjectTitle' => ['ObjectTitle', 255],
            'CurrentURL' => ['CurrentURL', 255],
            'Referer' => ['Referer', 255],
            'Agent' => ['Agent', 255],
            'RemoteIP' => ['RemoteIP', 128],
            'ChangeType' => ['ChangeType', 255],
        ];
    }

    /**
     * Over long values are cut by the database today, silently and without a warning
     *
     * @dataProvider longValueProvider
     */
    public function testLongValuesTruncated(string $field, int $limit)
    {
        $long = str_repeat('x', 400);
        $title = 'Long';
        switch ($field) {
            case 'ObjectTitle':
                $title = $long;
                break;
            case 'CurrentURL':
                $_SERVER['REQUEST_URI'] = '/' . $long;
                break;
            case 'Referer':
                $_SERVER['HTTP_REFERER'] = 'http://r.test/' . $long;
                break;
            case 'Agent':
                $_SERVER['HTTP_USER_AGENT'] = $long;
                break;
            case 'RemoteIP':
                $_SERVER['REMOTE_ADDR'] = $long;
                break;
        }

        if ($field === 'ChangeType') {
            $object = $this->makeObject('Owner');
            $this->trackService()->track($object, 'Custom ' . $long);
            $record = $this->lastRecord();
        } else {
            $plain = $this->makePlain($title);
            $record = $this->recordsFor($plain)[0];
        }

        $this->assertSame($limit, strlen($record->$field));
        $this->assertSame([], $this->takeWarnings());
    }

    public function testObjectTitleNonStringCast()
    {
        $plain = PlainRecordable::create(['Title' => 42]);
        $plain->write();

        $record = $this->lastRecord();
        $this->assertSame('42', $record->ObjectTitle);
    }

    public function testObjectWithoutTitle()
    {
        $plain = PlainRecordable::create(['Notes' => 'No title here']);
        $plain->write();

        $record = $this->lastRecord();
        $this->assertSame('New', $record->ChangeType);
        $this->assertNull($record->ObjectTitle);
    }

    public function testSaveRequestVarsDefaultOff()
    {
        $_GET = ['page' => '2'];
        $_POST = ['Title' => 'posted'];

        $plain = $this->makePlain('Request vars');

        $record = $this->recordsFor($plain)[0];
        $this->assertNull($record->GetVars);
        $this->assertNull($record->PostVars);
    }

    public function testSaveRequestVarsStoresAndStripsBlacklistedKeysFromGlobals()
    {
        Config::modify()->set(DataChangeRecord::class, 'save_request_vars', true);
        $get = $_GET;
        $post = $_POST;
        try {
            $_GET = ['page' => '2', 'url' => 'admin/pages'];
            $_POST = ['Title' => 'posted', 'SecurityID' => 'token'];

            $plain = $this->makePlain('Request vars stored');

            $record = $this->recordsFor($plain)[0];
            $this->assertSame('{"page":"2"}', $record->GetVars);
            $this->assertSame('{"Title":"posted"}', $record->PostVars);
            // the blacklisted keys are also removed from the live request globals
            $this->assertSame(['page' => '2'], $_GET);
            $this->assertSame(['Title' => 'posted'], $_POST);
        } finally {
            $_GET = $get;
            $_POST = $post;
        }
    }

    public function testGetDataChangesList()
    {
        $first = $this->makePlain('First');
        $second = $this->makePlain('Second');
        $second->Notes = 'edited';
        $second->write();

        $this->assertSame(1, $first->getDataChangesList()->count());
        $this->assertSame(
            ['New', 'Change'],
            $second->getDataChangesList()->sort('ID')->column('ChangeType')
        );
    }

    public function testStickyNewAfterCreateSameProcess()
    {
        $first = PlainRecordable::create(['Title' => 'First']);
        $first->write();
        $first->Title = 'First, renamed';
        $first->write();

        // the extension instance is shared and keeps "New" after the first create, so the rename is logged as new
        $this->assertSame(['New', 'New'], $this->types($first));

        $this->resetTracking();
        $first->Title = 'First, renamed again';
        $first->write();
        $this->assertSame(['New', 'New', 'Change'], $this->types($first));
    }

    public function testStickyNewIsSharedAcrossRecordsOfOtherClasses()
    {
        $plain = PlainRecordable::create(['Title' => 'Creator']);
        $plain->write();

        $other = $this->makePlain('Existing before');
        $this->resetTracking();
        $other->Title = 'Existing after';
        $other->write();
        $this->assertSame(['New', 'Change'], $this->types($other));

        // an update to an old record right after any create is mislabelled
        $this->resetTracking();
        $created = PlainRecordable::create(['Title' => 'Created']);
        $created->write();
        $other->Title = 'Existing, third title';
        $other->write();
        $this->assertSame(['New', 'Change', 'New'], $this->types($other));
    }

    public function testNewTypeWithNoChangesStillWrites()
    {
        $existing = $this->makePlain('Existing');

        $existing->write();
        $this->assertSame(['New'], $this->types($existing), 'A no-op write is skipped while the type is Change');

        // The shortcut for "nothing changed" only applies to the Change type, so with the sticky New type a no-op
        // write on an old record is stored with empty payloads
        PlainRecordable::create(['Title' => 'Creator'])->write();
        $existing->write();

        $this->assertSame(['New', 'New'], $this->types($existing));
        $record = $this->recordsFor($existing)[1];
        $this->assertSame('[]', $record->Before);
        $this->assertSame('[]', $record->After);
    }

    public function testVersionedPageTitleIsRecordedAsObjectTitle()
    {
        $page = TrackedPage::create(['Title' => 'A page']);
        $page->write();

        $this->assertSame('A page', $this->recordsFor($page)[0]->ObjectTitle);
        $this->assertSame(TrackedPage::class, $this->recordsFor($page)[0]->ChangeRecordClass);
    }

    public function testChildWithoutExtensionIsNeverTracked()
    {
        TrackedChild::create(['Title' => 'Untracked'])->write();

        $this->assertCount(0, $this->records());
    }
}
