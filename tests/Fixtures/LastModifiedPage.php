<?php

namespace Dynamic\ChangeTracker\Tests\Fixtures;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\TestOnly;
use Dynamic\ChangeTracker\Extension\LastModifiedExtension;

/**
 * A page that adds its last-modified meta tags through LastModifiedExtension, in the same place as the sites did
 */
class LastModifiedPage extends SiteTree implements TestOnly
{
    private static $table_name = 'LastModifiedPage';

    private static $has_one = [
        'Feature' => TrackedObject::class,
    ];

    private static $owns = [
        'Feature',
    ];

    private static $extensions = [
        LastModifiedExtension::class,
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

        $tags = $this->withLastModifiedMetaTags($tags);

        return $tags;
    }
}
