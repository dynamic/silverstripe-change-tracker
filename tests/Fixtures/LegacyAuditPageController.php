<?php

namespace Symbiote\DataChange\Tests\Fixtures;

use SilverStripe\Assets\File;
use SilverStripe\Assets\Folder;
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Dev\TestOnly;

/**
 * The page controller code of the sites before the module took over the review listings. The allowed actions and the
 * two methods are the sites' code, unchanged, and serve as the reference that AuditListingExtension must reproduce.
 */
class LegacyAuditPageController extends ContentController implements TestOnly
{
    /**
     * An array of actions that can be accessed via a request. Each array element should be an action name, and the
     * permissions or conditions required to allow the user to access it.
     *
     * <code>
     * [
     *     'action', // anyone can access this action
     *     'action' => true, // same as above
     *     'action' => 'ADMIN', // you must have ADMIN permissions to access this action
     *     'action' => '->checkAction' // you can only access this action if $this->checkAction() returns true
     * ];
     * </code>
     *
     * @var array
     */
    private static array $allowed_actions = [
        'listpages' => 'ADMIN',
        'listdocs' => 'ADMIN',
    ];

    /**
     * @return HTTPResponse
     * Lists pages for NIST review
     * Must be run from a subpage
     * Must be logged in as Admin
     */
    public function listpages(): HTTPResponse
    {
        $pages = SiteTree::get()->sort('LastEdited DESC');

        $html = '<table border="1" cellpadding="4" width="100%">';
        $html .= '<tr><th>ID</th><th>Title</th><th>URL</th><th>Modified (LastEdited)</th></tr>';

        foreach ($pages as $p) {
            $html .= sprintf(
                '<tr><td>%d</td><td>%s</td><td><a target="_blank" href="%s">%s</a></td><td>%s</td></tr>',
                $p->ID,
                htmlspecialchars((string)$p->Title, ENT_QUOTES, 'UTF-8'),
                $p->AbsoluteLink(),
                $p->AbsoluteLink(),
                $p->LastEdited
            );
        }

        $html .= '</table>';

        return HTTPResponse::create($html)
            ->addHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * @return HTTPResponse
     * List docs for NIST review
     * Must be run from a subpage
     * Must be logged in as Admin
     */
    public function listdocs(): HTTPResponse
    {
        // common document extensions
        $exts = ['pdf','doc','docx','xls','xlsx','ppt','pptx','csv','rtf','txt'];
        $suffixes = array_map(fn($e) => '.' . $e, $exts);

        $files = File::get()
            ->exclude('ClassName', Folder::class)
            ->filterAny(['FileFilename:EndsWith' => $suffixes])
            ->sort('LastEdited DESC');

        $html = '<table border="1" cellpadding="4" width="100%">';
        $html .= '<tr><th>ID</th><th>Title</th><th>Filename</th><th>URL</th><th>Size</th><th>Modified</th></tr>';

        foreach ($files as $f) {
            $html .= sprintf(
                '<tr><td>%d</td><td>%s</td><td>%s</td><td><a target="_blank" href="%s">%s</a></td><td>%s</td><td>%s</td></tr>',
                $f->ID,
                htmlspecialchars((string)$f->Title, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string)$f->Name, ENT_QUOTES, 'UTF-8'),
                $f->getAbsoluteURL(),
                $f->getAbsoluteURL(),
                method_exists($f, 'getSize') ? $f->getSize() : $f->getFileSize(),
                $f->LastEdited
            );
        }

        $html .= '</table>';

        return HTTPResponse::create($html)
            ->addHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
