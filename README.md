# Micro ACL

This is a Silverstripe module that lets you attach a simple ACL to your Silverstripe data objects. It's designed to
provide a more complete permission system based on the existing Permission codes, without requiring major refactoring
of the core framework to support it.

## Usage

First, install the module:
```
> composer require sminnee/silverstripe-microacl:^0.1
```

Then add the ACLExtension class to a data object:

```php
use SilverStripe\ORM\DataObject;
use Sminnee\MicroACL\ACLExtension;

class MyClass extends DataObject
{
    // ...

    private static $extensions = [
        ACLExtension::class,
    ];
}
```

Run `dev/build` and you're good to go!

## Permission model

The permission model currently has the following characteristics.

 * By default, every record has 'standard permissions'. This provides class-level security

 * Individual records can be set to having 'restricted permissions'. This provides record-level security.

 * Users (via Groups or Roles) can the granted the following permissions:
   * Access to standard items
   * Access to restricted items, by records
   * Access to all items, including all restricted items

 * Rights are broken down into view and edit permissions are defined.
   * You can delete anything that you can edit
   * Anyone who can edit a standard record can create records

 * In addition, each type has a permission code to administering permissions, which exposes the Access tab on records

All of the permission allocation is done through dynamically generated permission codes. This means that both groups
and roles can be assigned these rights, and that the admin/security/ area can provide a complete view of the access
rights granted (which is not the case with SiteTree_Viewers in the default CMS permission model)

 * `ACL_(DataClass)_(VIEW|EDIT)_DEFAULT`: standard access
 * `ACL_(DataClass)_(VIEW|EDIT)_RECORD.(RecordID)`: record-specific access
 * `ACL_(DataClass)_(VIEW|EDIT)_ALL`: full access
 * `ACL_(DataClass)_ACCESS_ADMIN`: administer access rights

## Future development

This module is an initial proof-of-concept and has a number of functional limitations that would be worth addressing:

 * Define permissions beyond view and edit.
 * Define cluster-level security. For example:
   * Of you have two data objects, Category and Item
   * And a Category has_many Item relationship
   * Then you may want to set permissions for all Items of a given Category
 * Define permissions based on a hierarchy, e.g. that found in SitTree. This is a special form of cluster-level
   security based on a recursive relationship.

The user interfaces are also far from optimal, and a re-thinking of the language and presentation of these controls
would be highly valuable.

## Limitations

Because permission codes are stored as strings, it is harder to efficiently create queries to, for exmaple, filter
a list to only those that the current member can view. Optimisation of batch queries (which is another problem with
the current system based on canView methods) wasn't a design goal of this module.
