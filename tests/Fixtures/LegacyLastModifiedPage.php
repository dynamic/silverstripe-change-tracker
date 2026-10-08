<?php

namespace Symbiote\DataChange\Tests\Fixtures;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * The page class of the sites before the module took over their last-modified meta tags. The method bodies are the
 * sites' code, unchanged, and serve as the reference that LastModifiedExtension must reproduce.
 */
class LegacyLastModifiedPage extends SiteTree implements TestOnly
{
    private static $table_name = 'LegacyLastModifiedPage';

    private static $has_one = [
        'Feature' => TrackedObject::class,
    ];

    private static $owns = [
        'Feature',
    ];

    /**
     * @return array
     */
    public function MetaComponents()
    {
        $tags = parent::MetaComponents();

        if ($this->MetaDescription) {
            $tags['og:description'] = [
                'attributes' => [
                    'name' => 'og:description',
                    'content' => $this->MetaDescription,
                ],
            ];
        }

        if ($lastModified = $this->LastModifiedMetaContent()) {
            $tags['lastModified'] = [
                'attributes' => [
                    'name' => 'last-modified',
                    'content' => $lastModified,
                ],
            ];
            $tags['articleModifiedTime'] = [
                'attributes' => [
                    'property' => 'article:modified_time',
                    'content' => $lastModified,
                ],
            ];
        }

        return $tags;
    }

    /**
     * Get the effective last-edited value in a meta-tag-friendly format.
     */
    protected function LastModifiedMetaContent(): ?string
    {
        $lastEdited = $this->EffectiveLastEdited();
        if (!$lastEdited) {
            return null;
        }

        return (new \DateTimeImmutable($lastEdited))->format(DATE_ATOM);
    }

    /**
     * Find the most recent change to this page or to a record it owns.
     */
    protected function EffectiveLastEdited(): ?string
    {
        $latestTimestamp = $this->LastEdited ? strtotime($this->LastEdited) : null;
        $latestValue = $this->LastEdited ?: null;

        if (!$this->hasMethod('findOwned')) {
            return $latestValue;
        }

        foreach ($this->findOwned() as $ownedRecord) {
            if (!$ownedRecord instanceof DataObject || !$ownedRecord->hasField('LastEdited')) {
                continue;
            }

            $ownedLastEdited = $ownedRecord->getField('LastEdited');
            if (!$ownedLastEdited) {
                continue;
            }

            $ownedTimestamp = strtotime($ownedLastEdited);
            if ($ownedTimestamp === false) {
                continue;
            }

            if ($latestTimestamp === null || $ownedTimestamp > $latestTimestamp) {
                $latestTimestamp = $ownedTimestamp;
                $latestValue = $ownedLastEdited;
            }
        }

        return $latestValue;
    }
}
