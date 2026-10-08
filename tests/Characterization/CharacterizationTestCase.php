<?php

namespace Symbiote\DataChange\Tests\Characterization;

use ReflectionProperty;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ManyManyList;
use SilverStripe\Versioned\Versioned;
use Symbiote\DataChange\Extension\ChangeRecordable;
use Symbiote\DataChange\Extension\SiteTreeChangeRecordable;
use Symbiote\DataChange\Model\DataChangeRecord;
use Symbiote\DataChange\Model\TrackedManyManyList;
use Symbiote\DataChange\Tests\Fixtures\ElementLike;
use Symbiote\DataChange\Tests\Fixtures\PlainRecordable;
use Symbiote\DataChange\Tests\Fixtures\PlainRecordableSubclass;
use Symbiote\DataChange\Tests\Fixtures\ThroughJoin;
use Symbiote\DataChange\Tests\Fixtures\ThroughOwner;
use Symbiote\DataChange\Tests\Fixtures\TrackedChild;
use Symbiote\DataChange\Tests\Fixtures\TrackedObject;
use Symbiote\DataChange\Tests\Fixtures\TrackedPage;
use Symbiote\DataChange\Tests\Fixtures\TrackedPageSubclass;
use Symbiote\DataChange\Tests\TestTextJSONFieldObject;
use Symbiote\DataChange\Tests\TestTrackedUnderscoreChild;
use Symbiote\DataChange\Tests\TestTrackedUnderscoreObject;

/**
 * Base class for the characterization tests.
 *
 * Everything in these tests pins the behaviour of the module as it is today, including its known defects. A later
 * change that alters an observable result is expected to flip the matching assertion on purpose.
 *
 * - $_SERVER is replaced with fixed values for the duration of each test and restored afterwards.
 * - Warnings are captured with an error handler. A test claims them with takeWarnings() and asserts on them; any
 *   warning a test has not claimed fails it in tearDown.
 * - Tracked records are created inside the tests, after an admin has been logged in, and are never loaded from a
 *   fixture file, so nothing is tracked before the environment is under control.
 */
abstract class CharacterizationTestCase extends FunctionalTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        TrackedPage::class,
        TrackedPageSubclass::class,
        TrackedObject::class,
        TrackedChild::class,
        PlainRecordable::class,
        PlainRecordableSubclass::class,
        ElementLike::class,
        ThroughJoin::class,
        ThroughOwner::class,
        TestTrackedUnderscoreObject::class,
        TestTrackedUnderscoreChild::class,
        TestTextJSONFieldObject::class,
    ];

    protected static $required_extensions = [
        SiteTree::class => [
            SiteTreeChangeRecordable::class,
        ],
    ];

    /**
     * Server variables set for every test. Individual tests may override or unset entries.
     *
     * @var array
     */
    protected static $serverDefaults = [
        'SERVER_NAME' => 'tracker.test',
        'SERVER_PORT' => '8080',
        'REQUEST_URI' => '/admin/pages/edit/EditForm/7/field/Pages?q=1',
        'HTTP_REFERER' => 'http://referrer.test/previous',
        'HTTP_USER_AGENT' => 'CharacterizationAgent/1.0',
        'REMOTE_ADDR' => '203.0.113.7',
    ];

    /**
     * Keys kept when a stored payload is snapshotted for a golden file. Core SiteTree columns vary between releases,
     * so only fields declared by the fixtures and a few stable page fields are compared.
     *
     * @var string[]
     */
    protected static $payloadKeys = [
        'ID',
        'ClassName',
        'RecordClassName',
        'Title',
        'URLSegment',
        'MenuTitle',
        'Subtitle',
        'Extra',
        'Body',
        'Notes',
        'Password',
        'Secret',
        'FeatureID',
        'PageID',
    ];

    /**
     * @var array
     */
    private $originalServer = [];

    /**
     * @var array[]
     */
    private $capturedWarnings = [];

    /**
     * @var string[] "Class:ID" => label
     */
    private $labels = [];

    /**
     * @var bool
     */
    private $handlerInstalled = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalServer = $_SERVER;
        foreach (['HTTPS', 'HTTP_REFERER', 'HTTP_USER_AGENT', 'REMOTE_ADDR', 'SERVER_PORT'] as $key) {
            unset($_SERVER[$key]);
        }
        foreach (static::$serverDefaults as $key => $value) {
            $_SERVER[$key] = $value;
        }

        $this->capturedWarnings = [];
        $this->labels = [];
        $this->installWarningCapture();

        Versioned::set_stage(Versioned::DRAFT);
        $this->resetTracking();
        $this->trackService()->disabled = false;
        $this->logInWithPermission('ADMIN');
    }

    protected function tearDown(): void
    {
        $this->removeWarningCapture();
        $leftover = $this->capturedWarnings;
        $this->capturedWarnings = [];
        $_SERVER = $this->originalServer;

        parent::tearDown();

        if ($leftover) {
            $this->fail('Unclaimed warnings: ' . json_encode($this->describeWarnings($leftover)));
        }
    }

    /**
     * Capture warnings and notices instead of letting the runner convert them to exceptions. Deprecations are left
     * to the runner.
     */
    private function installWarningCapture(): void
    {
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            if (!(error_reporting() & $errno)) {
                return false;
            }
            if (!in_array($errno, [E_WARNING, E_USER_WARNING, E_NOTICE, E_USER_NOTICE], true)) {
                return false;
            }
            $this->capturedWarnings[] = [
                'level' => $errno,
                'message' => $errstr,
                'file' => basename($errfile),
                'line' => $errline,
            ];
            return true;
        });
        $this->handlerInstalled = true;
    }

    private function removeWarningCapture(): void
    {
        if ($this->handlerInstalled) {
            restore_error_handler();
            $this->handlerInstalled = false;
        }
    }

    /**
     * Return and clear the warnings captured so far
     *
     * @return array[] each with level, message, file (base name) and line
     */
    protected function takeWarnings(): array
    {
        $warnings = $this->capturedWarnings;
        $this->capturedWarnings = [];
        return $warnings;
    }

    /**
     * Summarise warnings as "file: message" strings, which is what the tests pin
     *
     * @param array[] $warnings
     * @return string[]
     */
    protected function describeWarnings(array $warnings): array
    {
        return array_map(function ($warning) {
            return $warning['file'] . ': ' . $warning['message'];
        }, $warnings);
    }

    /**
     * @return \Symbiote\DataChange\Service\DataChangeTrackService
     */
    protected function trackService()
    {
        return singleton('DataChangeTrackService');
    }

    /**
     * Forget the per request cache and the state a tracked extension keeps between writes.
     *
     * The extension instances are shared by every owner and keep the change type of the last new record, so
     * without this a write that follows a create in the same process is recorded as "New".
     */
    protected function resetTracking(): void
    {
        $this->trackService()->resetChangeCache();

        foreach ([ChangeRecordable::class, SiteTreeChangeRecordable::class] as $class) {
            $extension = Injector::inst()->get($class);
            foreach (['changeType' => 'Change', 'isNewObject' => false] as $property => $value) {
                $reflection = new ReflectionProperty(ChangeRecordable::class, $property);
                $reflection->setAccessible(true);
                $reflection->setValue($extension, $value);
            }
        }
    }

    /**
     * Attach a stable name to a record, used by goldens and failure messages
     *
     * @param DataObject $record
     * @param string $label
     * @return DataObject
     */
    protected function label(DataObject $record, string $label)
    {
        $this->labels[get_class($record) . ':' . $record->ID] = $label;
        return $record;
    }

    protected function labelFor(string $class, $id): string
    {
        return self::shortName($class) . ':' . ($this->labels[$class . ':' . $id] ?? '?');
    }

    /**
     * @return DataChangeRecord[] oldest first
     */
    protected function records(): array
    {
        return DataChangeRecord::get()->sort('ID', 'ASC')->toArray();
    }

    /**
     * Records for one tracked record, oldest first
     *
     * @param DataObject $record
     * @return DataChangeRecord[]
     */
    protected function recordsFor(DataObject $record): array
    {
        return DataChangeRecord::get()
            ->filter(['ChangeRecordClass' => get_class($record), 'ChangeRecordID' => $record->ID])
            ->sort('ID', 'ASC')
            ->toArray();
    }

    /**
     * @param DataObject|null $record limit to one tracked record, or null for all
     * @return string[] change types, oldest first
     */
    protected function types(?DataObject $record = null): array
    {
        $rows = $record ? $this->recordsFor($record) : $this->records();
        return array_map(function ($row) {
            return $row->ChangeType;
        }, $rows);
    }

    protected function lastRecord(): ?DataChangeRecord
    {
        return DataChangeRecord::get()->sort('ID', 'DESC')->first();
    }

    /**
     * Strip the namespace of the test fixtures so goldens survive a namespace rename
     *
     * @param string $class
     * @return string
     */
    protected static function shortName(string $class): string
    {
        return preg_replace('/^(?:[A-Za-z0-9_]+\\\\)+Tests\\\\(?:Fixtures\\\\)?/', '', $class);
    }

    protected function makePage(string $title, array $data = []): TrackedPage
    {
        $page = TrackedPage::create(array_merge(['Title' => $title], $data));
        $page->write();
        $this->resetTracking();
        return $page;
    }

    protected function makeObject(string $title, array $data = []): TrackedObject
    {
        $object = TrackedObject::create(array_merge(['Title' => $title], $data));
        $object->write();
        $this->resetTracking();
        return $object;
    }

    protected function makeChild(string $title): TrackedChild
    {
        $child = TrackedChild::create(['Title' => $title]);
        $child->write();
        $this->resetTracking();
        return $child;
    }

    protected function makePlain(string $title, array $data = []): PlainRecordable
    {
        $plain = PlainRecordable::create(array_merge(['Title' => $title], $data));
        $plain->write();
        $this->resetTracking();
        return $plain;
    }

    /**
     * Route many_many lists through the tracked list, as the sites do, for the given join tables
     *
     * @param string[] $joinTables
     */
    protected function trackRelationships(array $joinTables): void
    {
        Injector::inst()->load([
            ManyManyList::class => [
                'class' => TrackedManyManyList::class,
                'properties' => [
                    'trackedRelationships' => $joinTables,
                ],
            ],
        ]);
    }

    /**
     * A plain description of every stored record, safe to compare with a golden file: fixture classes are
     * shortened, ids are replaced by the labels given with label(), and payloads are cut down to stable keys.
     *
     * @return array[]
     */
    protected function snapshotRecords(): array
    {
        $snapshot = [];
        foreach ($this->records() as $record) {
            $pages = array_map(function ($page) {
                return $this->labelFor(get_class($page), $page->ID);
            }, $record->AffectedPages()->toArray());
            sort($pages);

            $snapshot[] = [
                'ChangeType' => $record->ChangeType,
                'Record' => $this->labelFor($record->ChangeRecordClass, $record->ChangeRecordID),
                'ObjectTitle' => $record->ObjectTitle,
                'Stage' => $record->Stage,
                'User' => $record->ChangedByID ? 'member' : 'none',
                'CurrentEmail' => $record->CurrentEmail,
                'CurrentURL' => $record->CurrentURL,
                'Referer' => $record->Referer,
                'RemoteIP' => $record->RemoteIP,
                'Agent' => $record->Agent,
                'Before' => $this->snapshotPayload($record->Before),
                'After' => $this->snapshotPayload($record->After),
                'AffectedPages' => $pages,
            ];
        }
        return $snapshot;
    }

    /**
     * @param string|null $json
     * @return mixed
     */
    protected function snapshotPayload($json)
    {
        $data = json_decode((string)$json, true);
        if (!is_array($data)) {
            return $data;
        }

        $data = array_intersect_key($data, array_flip(static::$payloadKeys));
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = self::shortName($value);
            }
            if (substr($key, -2) === 'ID' && is_numeric($value)) {
                $data[$key] = (int)$value > 0 ? '#id' : 0;
            }
        }
        ksort($data);
        return $data;
    }
}
