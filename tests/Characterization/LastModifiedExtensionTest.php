<?php

namespace Symbiote\DataChange\Tests\Characterization;

use ReflectionMethod;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use Symbiote\DataChange\Extension\LastModifiedExtension;
use Symbiote\DataChange\Extension\SiteTreeChangeRecordable;
use Symbiote\DataChange\Tests\Fixtures\LastModifiedPage;
use Symbiote\DataChange\Tests\Fixtures\LegacyLastModifiedPage;
use Symbiote\DataChange\Tests\Fixtures\TrackedObject;
use Symbiote\DataChange\Tests\Fixtures\TrackedPage;

/**
 * LastModifiedExtension gives the same meta tags, in the same place, as the page code the sites carried. Every test
 * builds the same content twice, once on a page with the sites' code and once on a page that uses the extension.
 */
class LastModifiedExtensionTest extends CharacterizationTestCase
{
    protected static $required_extensions = [
        SiteTree::class => [
            SiteTreeChangeRecordable::class,
        ],
        TrackedPage::class => [
            LastModifiedExtension::class,
        ],
    ];

    public static function getExtraDataObjects()
    {
        return array_merge(parent::getExtraDataObjects(), [
            LegacyLastModifiedPage::class,
            LastModifiedPage::class,
        ]);
    }

    private function setLastEdited(DataObject $record, string $value): void
    {
        $table = DataObject::getSchema()->baseDataTable(get_class($record));
        DB::prepared_query(
            'UPDATE "' . $table . '" SET "LastEdited" = ? WHERE "ID" = ?',
            [$value, $record->ID]
        );
    }

    /**
     * The same page, built with the sites' code and with the extension
     *
     * @param string $pageEdited
     * @param string|null $featureEdited null for a page without an owned record
     * @param string $description
     * @return SiteTree[] [legacy, module], read back from the database
     */
    private function pair(string $pageEdited, ?string $featureEdited, string $description = ''): array
    {
        $pages = [];
        foreach ([LegacyLastModifiedPage::class, LastModifiedPage::class] as $class) {
            $page = $class::create(['Title' => 'Meta page', 'MetaDescription' => $description]);
            if ($featureEdited !== null) {
                $feature = $this->makeObject('Owned');
                $page->FeatureID = $feature->ID;
                $this->setLastEdited($feature, $featureEdited);
            }
            $page->write();
            $this->resetTracking();
            $this->setLastEdited($page, $pageEdited);
            $pages[] = $class::get()->byID($page->ID);
        }
        return $pages;
    }

    private function legacyEffective(LegacyLastModifiedPage $page): ?string
    {
        $method = new ReflectionMethod($page, 'EffectiveLastEdited');
        $method->setAccessible(true);
        return $method->invoke($page);
    }

    /**
     * @return array[] scenario name => [page LastEdited, owned LastEdited or null]
     */
    public function scenarioProvider(): array
    {
        return [
            'no owned record' => ['2030-01-02 03:04:05', null],
            'owned record edited later' => ['2030-01-02 03:04:05', '2031-06-07 08:09:10'],
            'owned record edited earlier' => ['2030-01-02 03:04:05', '2029-12-31 23:59:59'],
            'same time' => ['2030-01-02 03:04:05', '2030-01-02 03:04:05'],
        ];
    }

    /**
     * @dataProvider scenarioProvider
     */
    public function testEffectiveLastEditedMatchesTheSitesCode(string $pageEdited, ?string $featureEdited)
    {
        [$legacy, $module] = $this->pair($pageEdited, $featureEdited);

        $expected = $featureEdited !== null && $featureEdited > $pageEdited ? $featureEdited : $pageEdited;
        $this->assertSame($expected, $this->legacyEffective($legacy));
        $this->assertSame($this->legacyEffective($legacy), $module->EffectiveLastEdited());
    }

    /**
     * @dataProvider scenarioProvider
     */
    public function testMetaComponentsMatchTheSitesCode(string $pageEdited, ?string $featureEdited)
    {
        $this->logOut();
        [$legacy, $module] = $this->pair($pageEdited, $featureEdited, 'A description');

        $this->assertSame($legacy->MetaComponents(), $module->MetaComponents());
        $this->assertSame($legacy->MetaTags(false), $module->MetaTags(false));
    }

    public function testMetaTagsHtmlExact()
    {
        $this->logOut();
        [$legacy, $module] = $this->pair('2030-01-02 03:04:05', '2031-06-07 08:09:10', 'A description');

        $expected = (new \DateTimeImmutable('2031-06-07 08:09:10'))->format(DATE_ATOM);
        $html = $module->MetaTags(false);
        $this->assertStringEndsWith(
            '<meta name="og:description" content="A description">' . "\n"
            . '<meta name="last-modified" content="' . $expected . '">' . "\n"
            . '<meta property="article:modified_time" content="' . $expected . '">',
            trim($html)
        );
        $this->assertSame($legacy->MetaTags(false), $html);
    }

    public function testWithLastModifiedMetaTagsArrayShape()
    {
        [, $module] = $this->pair('2030-01-02 03:04:05', null);
        $content = (new \DateTimeImmutable('2030-01-02 03:04:05'))->format(DATE_ATOM);
        $this->assertMatchesRegularExpression('/^2030-01-02T03:04:05[+-]\d\d:\d\d$/', $content);

        $tags = $module->withLastModifiedMetaTags([
            'first' => ['tag' => 'title', 'content' => 'x'],
            'articleModifiedTime' => ['tag' => 'meta'],
            'last' => ['tag' => 'meta'],
        ]);

        // an existing key keeps its place and gets the new value, new keys go at the end
        $this->assertSame(['first', 'articleModifiedTime', 'last', 'lastModified'], array_keys($tags));
        $this->assertSame(
            ['attributes' => ['name' => 'last-modified', 'content' => $content]],
            $tags['lastModified']
        );
        $this->assertSame(
            ['attributes' => ['property' => 'article:modified_time', 'content' => $content]],
            $tags['articleModifiedTime']
        );
    }

    public function testNoTagsWithoutAValue()
    {
        $page = LastModifiedPage::create(['Title' => 'Never written']);

        $this->assertNull($page->EffectiveLastEdited());
        $this->assertSame(['x' => 1], $page->withLastModifiedMetaTags(['x' => 1]));
    }

    public function testExtensionAddsNoTagsByItself()
    {
        $page = $this->makePage('Only the extension');

        $this->assertTrue($page->hasExtension(LastModifiedExtension::class));
        $this->assertNotNull($page->EffectiveLastEdited());
        $this->assertArrayNotHasKey('lastModified', $page->MetaComponents());
        $this->assertArrayNotHasKey('articleModifiedTime', $page->MetaComponents());
    }
}
