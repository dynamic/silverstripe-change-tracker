<?php

namespace Symbiote\DataChange\Extension;

use DateTimeImmutable;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\DataObject;

/**
 * "Last modified" for a page that takes the records it owns into account, and the meta tags that publish it.
 *
 * The extension adds methods only and hooks into nothing, so the tags appear exactly where the page puts them:
 *
 *     public function MetaComponents()
 *     {
 *         $tags = parent::MetaComponents();
 *         // ... the page's own tags
 *         $tags = $this->withLastModifiedMetaTags($tags);
 *         return $tags;
 *     }
 */
class LastModifiedExtension extends Extension
{
    /**
     * Add the last-modified and article:modified_time meta tags, when a value is known
     *
     * @param array $tags meta components, as returned by SiteTree::MetaComponents()
     * @return array
     */
    public function withLastModifiedMetaTags(array $tags): array
    {
        if ($lastModified = $this->lastModifiedMetaContent()) {
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
     * Find the most recent change to this page or to a record it owns.
     *
     * @return string|null the LastEdited value of the most recently edited record
     */
    public function EffectiveLastEdited(): ?string
    {
        $owner = $this->getOwner();
        $latestTimestamp = $owner->LastEdited ? strtotime($owner->LastEdited) : null;
        $latestValue = $owner->LastEdited ?: null;

        if (!$owner->hasMethod('findOwned')) {
            return $latestValue;
        }

        foreach ($owner->findOwned() as $ownedRecord) {
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

    /**
     * Get the effective last-edited value in a meta-tag-friendly format.
     */
    protected function lastModifiedMetaContent(): ?string
    {
        $lastEdited = $this->EffectiveLastEdited();
        if (!$lastEdited) {
            return null;
        }

        return (new DateTimeImmutable($lastEdited))->format(DATE_ATOM);
    }
}
