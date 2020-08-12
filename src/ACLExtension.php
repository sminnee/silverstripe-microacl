<?php

namespace Sminnee\MicroACL;

use SilverStripe\Security\PermissionRoleCode;
use SilverStripe\Security\PermissionRole;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Group;
use SilverStripe\ORM\SS_List;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\ArrayList;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\ClassInfo;

class ACLExtension extends DataExtension implements PermissionProvider
{

    private static $db = [
        'PermissionModel' => 'Enum("ClassLevel,RecordLevel")',
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $fields->removeByName('PermissionModel');

        if (Permission::check('ACL_' . get_class($this->owner) . '_ACCESS_ADMIN')) {
            $fields->addFieldsToTab('Root.Access', [
                new OptionsetField('PermissionModel', 'Permission model', [
                    'ClassLevel' => 'Standard permissons',
                    'RecordLevel' => 'Restricted permisssions'
                ]),

                new HeaderField('Can View'),
                new ReadonlyField('ViewGroups', 'Groups'),
                new ReadonlyField('ViewRoles', 'Roles'),

                new HeaderField('Can Edit'),
                new ReadonlyField('EditGroups', 'Groups'),
                new ReadonlyField('EditRoles', 'Roles'),
            ]);

            if ($this->groupsWithPermission('VIEW') instanceof ArrayList) {
                $fields->removeByName('ViewGroups');
            }
            if ($this->groupsWithPermission('EDIT') instanceof ArrayList) {
                $fields->removeByName('EditGroups');
            }
            if ($this->rolesWithPermission('VIEW') instanceof ArrayList) {
                $fields->removeByName('ViewRoles');
            }
            if ($this->rolesWithPermission('EDIT') instanceof ArrayList) {
                $fields->removeByName('EditRoles');
            }
        }
    }

    public function getViewGroups()
    {
        return implode(', ', $this->groupsWithPermission('VIEW')->column('Title'));
    }
    public function getEditGroups()
    {
        return implode(', ', $this->groupsWithPermission('EDIT')->column('Title'));
    }
    public function getViewRoles()
    {
        return implode(', ', $this->rolesWithPermission('VIEW')->column('Title'));
    }
    public function getEditRoles()
    {
        return implode(', ', $this->rolesWithPermission('EDIT')->column('Title'));
    }

    public function canView($member)
    {
        return Permission::checkMember($member, $this->getPermissionCodes('VIEW'));
    }

    public function canCreate($member, $context = [])
    {
        return Permission::checkMember($member, $this->getPermissionCodes('EDIT'));
    }

    public function canEdit($member)
    {
        return Permission::checkMember($member, $this->getPermissionCodes('EDIT'));
    }

    public function canDelete($member)
    {
        return Permission::checkMember($member, $this->getPermissionCodes('EDIT'));
    }

    /**
     * Return the groups with the given access right granted
     * Excldues groups that have the right granted via role
     */
    protected function groupsWithPermission(string $permission): SS_List
    {
        $ids = Permission::get()
            ->filter('Code', array_merge($this->getPermissionCodes($permission), ['ADMIN']))
            ->column('GroupID');

        if ($ids) {
            return Group::get()->filter('ID', $ids);
        }

        return new ArrayList();
    }

    /**
     * Return the roles with the given access right granted
     */
    protected function rolesWithPermission(string $permission): SS_List
    {
        $ids = PermissionRoleCode::get()
            ->filter('Code', array_merge($this->getPermissionCodes($permission), ['ADMIN']))
            ->column('RoleID');

        if ($ids) {
            return PermissionRole::get()->filter('ID', $ids);
        }

        return new ArrayList();
    }

    /**
     * Return the permission codes that will grant the given access right
     */
    protected function getPermissionCodes(string $permission): array
    {
        $class = get_class($this->owner);
        $permissionBase = 'ACL_' . $class . '_' . $permission;

        switch ($this->owner->PermissionModel) {
            case 'RecordLevel':
                return [$permissionBase . '_ALL', $permissionBase . '_RECORD.' . $this->owner->ID];

            case 'ClassLevel':
            default:
                return [$permissionBase . '_ALL', $permissionBase . '_DEFAULT'];
        }

        return $codes;
    }

    public function providePermissions()
    {
        $permissions = [];

        $dataClasses = ClassInfo::subclassesFor(DataObject::class);
        foreach ($dataClasses as $dataClass) {
            if (Extensible::has_extension($dataClass, self::class)) {
                $permissions += $this->providePermissionsFor($dataClass, 'VIEW', 'View');
                $permissions += $this->providePermissionsFor($dataClass, 'EDIT', 'Edit');

                $classLabel = singleton($dataClass)->plural_name();
                $permissions['ACL_' . $dataClass . '_ACCESS_ADMIN'] = [
                    'category' => "$classLabel (C): access all items, restricted and standard",
                    'name' => "View/Edit permissions for $classLabel",
                    'sort' => 100,
                ];
            }
        }

        return $permissions;
    }

    /**
     * Provide permissions for one permission type on one class
     */
    private function providePermissionsFor(string $class, string $permission, string $label)
    {
        $permissionBase = 'ACL_' . $class . '_' . $permission;
        $classLabel = singleton($class)->plural_name();
        $singularLabel = singleton($class)->singular_name();

        // Note that the sprintfs() are english-specific string-concatenation that shouldn't be exposed to other translations

        $category1 = "$classLabel (A): access standard items";
        $category2 = "$classLabel (B): access restricted items - record-specific";
        $category3 = "$classLabel (C): access all items, restricted and standard";

        // Base permissions
        $result = [
            $permissionBase . '_DEFAULT' => [
                'category' => $category1,
                'name' => _t(
                    $permissionBase . '_DEFAULT',
                    sprintf('%s standard %s', $label, $classLabel)
                ),
            ],
            $permissionBase . '_ALL' => [
                'category' => $category3,
                'name' => _t(
                    $permissionBase . '_ALL',
                    sprintf('%s all %s', $label, $classLabel)
                ),
            ],
        ];

        // Record-specific permissions
        $secureRecords = DataObject::get($class)->filter('PermissionModel', 'RecordLevel');
        foreach ($secureRecords as $record) {
            $permissionCodeName = _t(
                $permissionBase . '_RECORD',
                sprintf('{title} - %s', $label),
                ['title' => $record->Title ]
            );

            $result[$permissionBase . '_RECORD.' . $record->ID] = ['category' => $category2, 'name' => $permissionCodeName];
        }

        return $result;
    }
}
