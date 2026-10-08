<?php

namespace Dynamic\ChangeTracker\Model;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Security\Security;
use SilverStripe\View\Requirements;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ToggleCompositeField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Versioned\DataDifferencer;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Security\Member;
use SilverStripe\Control\Director;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\Queries\SQLUpdate;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionRoleCode;
use Dynamic\ChangeTracker\Admin\DataChangeAdmin;
use Dynamic\ChangeTracker\Job\PruneChangesBeforeJob;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;

/**
 * Record a change to a dataobject; use this to track data changes of objects
 *
 * @author  marcus@symbiote.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class DataChangeRecord extends DataObject
{
    /**
     * Class name of this model in symbiote/silverstripe-datachange-tracker and the Dynamic fork of it
     */
    public const LEGACY_CLASS = 'Symbiote\\DataChange\\Model\\DataChangeRecord';

    /**
     * Permission code the Data Changes admin required in those packages, derived from its class name
     */
    public const LEGACY_PERMISSION_CODE = 'CMS_ACCESS_Symbiote\\DataChange\\Admin\\DataChangeAdmin';

    /**
     * Class name of the pruning job in those packages, as stored in queued job descriptors
     */
    public const LEGACY_PRUNE_JOB_CLASS = 'Symbiote\\DataChange\\Job\\PruneChangesBeforeJob';

    private static $table_name = 'DataChangeRecord';
    private static $db = [
        'ChangeType' => 'Varchar',
        'ObjectTitle' => 'Varchar(255)',
        'Before' => 'Text',
        'After' => 'Text',
        'Stage' => 'Text',
        'CurrentEmail' => 'Text',
        'CurrentURL' => 'Varchar(255)',
        'Referer' => 'Varchar(255)',
        'RemoteIP' => 'Varchar(128)',
        'Agent' => 'Varchar(255)',
        'GetVars' => 'Text',
        'PostVars' => 'Text',
    ];
    private static $has_one = [
        'ChangedBy' => Member::class,
        'ChangeRecord' => DataObject::class
    ];

    private static $many_many = [
        'AffectedPages' => SiteTree::class,
    ];

    private static $summary_fields    = [
        'ChangeType' => 'Change Type',
        'ChangeRecordClass' => 'Record Class',
        'ChangeRecordID' => 'Record ID',
        'ObjectTitle' => 'Record Title',
        'ChangedBy.Title' => 'User',
        'Created' => 'Modification Date'
    ];
    private static $searchable_fields = [
        'ChangeType',
        'ObjectTitle',
        'ChangeRecordClass',
        'ChangeRecordID'
    ];
    private static $default_sort      = 'ID DESC';

    /**
     * Should request variables be saved too?
     *
     * @var boolean
     */
    private static $save_request_vars      = false;
    /**
     * Fields never stored in the before and after values of a change. SearchContent is the full text search index
     * that every site using this module excluded in its own configuration.
     *
     * @var string[]
     */
    private static $field_blacklist        = ['Password', 'SearchContent'];
    private static $request_vars_blacklist = ['url', 'SecurityID'];

    public function getCMSFields($params = null)
    {
        Requirements::css('dynamic/silverstripe-change-tracker: client/css/datachange-tracker.css');

        $fields = FieldList::create(
            ToggleCompositeField::create(
                'Details',
                'Details',
                [
                    ReadonlyField::create('ChangeType', 'Type of change'),
                    ReadonlyField::create('ChangeRecordClass', 'Record Class'),
                    ReadonlyField::create('ChangeRecordID', 'Record ID'),
                    ReadonlyField::create('ObjectTitle', 'Record Title'),
                    ReadonlyField::create('Created', 'Modification Date'),
                    ReadonlyField::create('Stage', 'Stage'),
                    ReadonlyField::create('User', 'User', $this->getMemberDetails()),
                    ReadonlyField::create('CurrentURL', 'URL'),
                    ReadonlyField::create('Referer', 'Referer'),
                    ReadonlyField::create('RemoteIP', 'Remote IP'),
                    ReadonlyField::create('Agent', 'Agent')
                ]
            )->setStartClosed(false)->addExtraClass('datachange-field'),
            ToggleCompositeField::create(
                'RawData',
                'Raw Data',
                [
                    ReadonlyField::create('Before'),
                    ReadonlyField::create('After'),
                    ReadonlyField::create('GetVars'),
                    ReadonlyField::create('PostVars')
                ]
            )->setStartClosed(false)->addExtraClass('datachange-field')
        );

        if (strlen($this->Before) && strlen($this->ChangeRecordClass) && class_exists($this->ChangeRecordClass)) {
            $decodedBefore = json_decode($this->Before);
            $decodedAfter  = json_decode($this->After);

            $before = Injector::inst()->create($this->ChangeRecordClass, $decodedBefore);
            $after  = Injector::inst()->create($this->ChangeRecordClass, $decodedAfter);
            $diff   = DataDifferencer::create($before, $after);

            // The solr search service injector dependency causes issues with comparison, since it has public variables that are stored in an array.

            $diff->ignoreFields(['searchService']);
            $diffed   = $diff->diffedData();
            $diffText = '';

            $changedFields = [];
            foreach ($diffed->toMap() as $field => $prop) {
                if (is_object($prop)) {
                    continue;
                }
                if (is_array($prop)) {
                    $prop = json_encode($prop);
                }

                if ((is_object($decodedBefore) && !array_key_exists($field, get_object_vars($decodedBefore))) || (is_object($decodedAfter) && !array_key_exists($field, get_object_vars($decodedAfter)))) {
                    continue;
                }

                $changedFields[] = $readOnly        = \SilverStripe\Forms\ReadonlyField::create(
                    'ChangedField' . $field,
                    $field,
                    DBField::create_field('HTMLText', $prop)
                );
                $readOnly->addExtraClass('datachange-field');
            }

            $fields->insertBefore(
                'RawData',
                ToggleCompositeField::create('FieldChanges', 'Changed Fields', $changedFields)
                    ->setStartClosed(false)
                    ->addExtraClass('datachange-field')
            );
        }

        // Flags fields that cannot be rendered with 'forTemplate'. This prevents bugs where
        // WorkflowService (of AdvancedWorkflow Module) and BlockManager (of Sheadawson/blocks module) get put
        // into a field and break the page.
        $fieldsToRemove = [];
        foreach ($fields->dataFields() as $field) {
            $value = $field->Value();
            if ($value && is_object($value)) {
                if (
                    (method_exists($value, 'hasMethod') && !$value->hasMethod('forTemplate')) || !method_exists(
                        $value,
                        'forTemplate'
                    )
                ) {
                    $field->setValue('[Missing ' . $value::class . '::forTemplate]');
                }
            }
        }

        $this->extend('updateCMSFields', $fields);

        $fields = $fields->makeReadonly();

        return $fields;
    }

    /**
     * Track a change to a DataObject
     *
     * @return DataChangeRecord
     * */
    public function track(DataObject $changedObject, $type = 'Change')
    {
        $changes = $changedObject->getChangedFields(true, 2);
        if (count($changes)) {
            // remove any changes to ignored fields
            $ignored = $changedObject->hasMethod('getIgnoredFields') ? $changedObject->getIgnoredFields() : null;
            if ($ignored) {
                $changes = array_diff_key($changes, $ignored);
                foreach ($ignored as $ignore) {
                    if (isset($changes[$ignore])) {
                        unset($changes[$ignore]);
                    }
                }
            }
        }

        foreach (self::config()->field_blacklist as $key) {
            if (isset($changes[$key])) {
                unset($changes[$key]);
            }
        }

        if ((empty($changes) && $type == 'Change')) {
            return;
        }

        if ($type === 'Delete' && Versioned::get_reading_mode() === 'Stage.Live') {
            $type = 'Delete from Live';
        }

        $this->ChangeType = $type;

        $this->ChangeRecordClass = $changedObject->ClassName;
        $this->ChangeRecordID    = $changedObject->ID;
        // @TODO this will cause issue for objects without titles
        $this->ObjectTitle       = $changedObject->Title;
        $this->Stage             = Versioned::get_reading_mode();

        $before = [];
        $after  = [];

        if ($type != 'Change' && $type != 'New') { // If we are (un)publishing we want to store the entire object
            $before = ($type === 'Unpublish') ? $changedObject->toMap() : null;
            $after  = ($type === 'Publish') ? $changedObject->toMap() : null;
        } else { // Else we're tracking the changes to the object
            foreach ($changes as $field => $change) {
                if ($field == 'SecurityID') {
                    continue;
                }
                $before[$field] = $change['before'];
                $after[$field]  = $change['after'];
            }
        }

        if ($this->Before && $this->Before !== 'null' && is_array($before)) {
            //merge the old array last to keep it's value as we want keep the earliest version of each field
            $this->Before = json_encode(array_replace(json_decode($this->Before, true), $before));
        } else {
            $this->Before = json_encode($before);
        }
        if ($this->After && $this->After !== 'null' && is_array($after)) {
            //merge the new array last to keep it's value as we want the newest version of each field
            $this->After = json_encode(array_replace($after, json_decode($this->After, true)));
        } else {
            $this->After = json_encode($after);
        }

        if (self::config()->save_request_vars) {
            foreach (self::config()->request_vars_blacklist as $key) {
                unset($_GET[$key]);
                unset($_POST[$key]);
            }

            $this->GetVars  = isset($_GET) ? json_encode($_GET) : null;
            $this->PostVars = isset($_POST) ? json_encode($_POST) : null;
        }

        if ($member = Security::getCurrentUser()) {
            $this->ChangedByID = $member->ID;
            $this->CurrentEmail = $member->Email;
        }

        if (isset($_SERVER['SERVER_NAME'])) {
            $protocol = 'http';
            $protocol = isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on" ? 'https://' : 'http://';
            $port = $_SERVER['SERVER_PORT'] ?? '80';

            $this->CurrentURL = $protocol . $_SERVER["SERVER_NAME"] . ":" . $port . $_SERVER["REQUEST_URI"];
        } elseif (Director::is_cli()) {
            $this->CurrentURL = 'CLI';
        } else {
            $this->CurrentURL = 'Could not determine current URL';
        }

        $this->RemoteIP = $_SERVER['REMOTE_ADDR'] ?? (Director::is_cli() ? 'CLI' : 'Unknown remote addr');
        $this->Referer  = $_SERVER['HTTP_REFERER'] ?? '';
        $this->Agent    = $_SERVER['HTTP_USER_AGENT'] ?? '';

        foreach (['ChangeType', 'ObjectTitle', 'CurrentURL', 'Referer', 'RemoteIP', 'Agent'] as $field) {
            $this->$field = $this->truncateToFieldSize($field, $this->$field);
        }

        $this->write();

        if ($this->hasMethod('getAffectedPageRecords')) {
            $this->AffectedPages()->addMany($this->getAffectedPageRecords() ?? []);
        }

        return $this;
    }

    /**
     * Bring data written by symbiote/silverstripe-datachange-tracker, or the Dynamic fork of it, in line with this
     * module. Each statement is limited to one table and only touches rows that still hold a legacy value, so running
     * the build again changes nothing.
     *
     * - ClassName of change records: rows that still name the legacy class, or that were written with an empty value
     *   while the old and new code were swapped, get the class of this model. The ClassName remapping in
     *   _config/legacy.yml covers the first case during the build; this repeats it for rows written afterwards.
     * - Permission and PermissionRoleCode: the legacy admin access code becomes the code DataChangeAdmin requires.
     * - QueuedJobDescriptor, when the queued jobs module is installed: pending pruning jobs get the new class name.
     */
    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();

        $schema = DataObject::getSchema();

        $this->migrateLegacyValue(
            $schema->tableName(DataChangeRecord::class),
            'ClassName',
            ['', self::LEGACY_CLASS],
            DataChangeRecord::class,
            'change records'
        );

        $code = DataChangeAdmin::config()->get('required_permission_codes');
        if (is_string($code) && $code !== self::LEGACY_PERMISSION_CODE) {
            foreach ([Permission::class, PermissionRoleCode::class] as $class) {
                $this->migrateLegacyValue(
                    $schema->tableName($class),
                    'Code',
                    [self::LEGACY_PERMISSION_CODE],
                    $code,
                    'permission codes'
                );
            }
            // drop permissions cached in this process before the codes changed
            Permission::reset();
        }

        if (class_exists(QueuedJobDescriptor::class)) {
            $table = $schema->tableName(QueuedJobDescriptor::class);
            if (DB::get_schema()->hasTable($table)) {
                $this->migrateLegacyValue(
                    $table,
                    'Implementation',
                    [self::LEGACY_PRUNE_JOB_CLASS],
                    PruneChangesBeforeJob::class,
                    'queued pruning jobs'
                );
            }
        }
    }

    /**
     * @param string $table
     * @param string $column
     * @param string[] $legacyValues
     * @param string $value
     * @param string $description for the build output
     */
    private function migrateLegacyValue(
        string $table,
        string $column,
        array $legacyValues,
        string $value,
        string $description
    ): void {
        $placeholders = implode(', ', array_fill(0, count($legacyValues), '?'));
        SQLUpdate::create('"' . $table . '"', ['"' . $column . '"' => $value])
            ->addWhere(['"' . $column . '" IN (' . $placeholders . ')' => $legacyValues])
            ->execute();

        $count = DB::affected_rows();
        if ($count > 0) {
            DB::alteration_message("Updated $count legacy $description in $table.$column", 'changed');
        }
    }

    /**
     * Cut a value to the size of its column, counting characters, which is what the database did with longer values
     * so far. Scalar values are stored as strings; null and other values are left alone.
     *
     * @param string $field
     * @param mixed $value
     * @return mixed
     */
    private function truncateToFieldSize(string $field, $value)
    {
        if (!is_scalar($value) && !$value instanceof \Stringable) {
            return $value;
        }
        $value = (string)$value;
        $size = (int)$this->dbObject($field)->getSize();
        return $size > 0 ? mb_substr($value, 0, $size, 'UTF-8') : $value;
    }

    /**
     * @return boolean
     * */
    public function canDelete($member = null)
    {
        return false;
    }

    /**
     * @return string
     * */
    public function getTitle()
    {
        return $this->ChangeRecordClass . ' #' . $this->ChangeRecordID;
    }

    /**
     * Return a description/summary of the user
     *
     * @return string
     * */
    public function getMemberDetails()
    {
        if ($user = $this->ChangedBy()) {
            $name = $user->getTitle();
            if ($user->Email) {
                $name .= " <$user->Email>";
            }
            return $name;
        }
    }

    private function prepareForDataDifferencer($jsonData)
    {
        // NOTE(Jake): 2018-06-21
        //
        // Data Differencer cannot handle arrays within an array,
        //
        // So JSON data that comes from MultiValueField / Text DB fields
        // causes errors to be thrown.
        //
        // So solve this, we simply only decode to a depth of 1. (rather than the 512 default)
        //
        $resultJsonData = json_decode((string) $jsonData, true, 1);
        return $resultJsonData;
    }
}
