<?php

namespace Dynamic\ChangeTracker\Control;

use SilverStripe\Assets\File;
use SilverStripe\Assets\Folder;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extension;

/**
 * Two listings for a periodic content review, added to a page controller: every page with its LastEdited value
 * ("listpages") and every document file ("listdocs"), newest first, as plain HTML tables.
 *
 * Apply it to the page controller and call it below any page, for example /about-us/listpages. Both actions require
 * ADMIN. Actions that come from an extension are checked against the extension's allowed_actions, so a different
 * permission code is configured on this class, not on the controller.
 */
class AuditListingExtension extends Extension
{
    use Configurable;

    /**
     * @config
     * @var array
     */
    private static $allowed_actions = [
        'listpages' => 'ADMIN',
        'listdocs' => 'ADMIN',
    ];

    /**
     * File extensions listed by listdocs, without the dot
     *
     * @config
     * @var string[]
     */
    private static $document_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'rtf', 'txt'];

    /**
     * Lists pages for review
     *
     * @return HTTPResponse
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
     * Lists documents for review
     *
     * @return HTTPResponse
     */
    public function listdocs(): HTTPResponse
    {
        $exts = (array)static::config()->get('document_extensions');
        $suffixes = array_map(fn($e) => '.' . $e, $exts);

        $files = File::get()
            ->exclude('ClassName', Folder::class)
            ->filterAny(['FileFilename:EndsWith' => $suffixes])
            ->sort('LastEdited DESC');

        $html = '<table border="1" cellpadding="4" width="100%">';
        $html .= '<tr><th>ID</th><th>Title</th><th>Filename</th><th>URL</th><th>Size</th><th>Modified</th></tr>';

        foreach ($files as $f) {
            $html .= sprintf(
                '<tr><td>%d</td><td>%s</td><td>%s</td><td><a target="_blank" href="%s">%s</a></td><td>%s</td>'
                . '<td>%s</td></tr>',
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
