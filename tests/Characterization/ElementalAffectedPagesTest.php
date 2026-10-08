<?php

namespace Symbiote\DataChange\Tests\Characterization;

use DNADesign\Elemental\Extensions\ElementalPageExtension;
use DNADesign\Elemental\Models\BaseElement;
use DNADesign\Elemental\Models\ElementContent;
use DNADesign\Elemental\Models\ElementalArea;
use Symbiote\DataChange\Tests\Fixtures\TrackedPage;

/**
 * The element branch of the affected pages lookup. It needs the elemental module and is skipped without it.
 */
class ElementalAffectedPagesTest extends CharacterizationTestCase
{
    public static function getRequiredExtensions()
    {
        $extensions = parent::getRequiredExtensions();
        if (class_exists(BaseElement::class)) {
            $extensions[TrackedPage::class] = [ElementalPageExtension::class];
        }
        return $extensions;
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists(BaseElement::class)) {
            $this->markTestSkipped('dnadesign/silverstripe-elemental is not installed');
        }
    }

    public function testElementalRelation()
    {
        $page = $this->makePage('Page with elements');
        $area = ElementalArea::create();
        $area->write();
        $page->ElementalAreaID = $area->ID;
        $page->write();
        $this->resetTracking();
        $element = ElementContent::create(['Title' => 'An element', 'ParentID' => $area->ID]);
        $element->write();
        $this->resetTracking();

        // an element is found through getPage(), which resolves the area's owner
        $record = $this->trackService()->track($element, 'Publish');

        $this->assertSame('Publish', $record->ChangeType);
        $this->assertSame([(int)$page->ID], array_map('intval', $record->AffectedPages()->column('ID')));
        $this->assertSame([(int)$page->ID], array_map(function ($affected) {
            return (int)$affected->ID;
        }, array_values($record->getAffectedPageRecords())));
    }
}
