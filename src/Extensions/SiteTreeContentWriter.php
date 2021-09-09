<?php

namespace Sunnysideup\CollectElementalContent\Extensions;

use SilverStripe\CMS\Model\SiteTreeExtension;

use SilverStripe\Core\Config\Config;

use DNADesign\Elemental\Models\ElementalArea;

class SiteTreeContentWriter extends SiteTreeExtension
{

    private const BASE_UNSEARCHABLE_FIELDS = [
        'Content',
        'ParentID',
        'Parent',
        'Type',
        'Created',
        'LastEdited',
        'ID',
        'ClassName',
        'Owner',
        'ElementalArea',
        'SortBy',
        'FileTracking',
        'LinkTracking',
        'ExtraClass',
        'Sort',
        'Version',
    ];

    private const BASE_UNSEARCHABLE_TYPES = [
        'Boolean',
        'Boolean(0)',
        'Boolean(1)',
        'Int',
        'Date',
    ];

    public function onBeforeWrite()
    {
        $this->updateSearchContentFromElementals();
    }

    public function updateSearchContentFromElementals()
    {
        //populate search
        $owner = $this->getOwner();
        $myContent = '';
        $myContent .= $owner->updateSearchContentFromElementalsExtractData($owner);
        $myElementalArea = $owner->ElementalArea();
        $ids = [];
        if ($myElementalArea && $myElementalArea instanceof ElementalArea) {
            $elements = $myElementalArea->Elements();
            foreach ($elements as $element) {
                $myContent .= $owner->updateSearchContentFromElementalsExtractData($element);
                $ids[$element->ID] = $element->Title;
            }
        }
        $this->owner->Content = $myContent . '. ';

        //for anchor links only!
        foreach($ids as $id => $title) {
            $this->owner->Content .= '<br /><a id="e'.$id.'">'.$title.'</a>';
        }
    }

    public function updateSearchContentFromElementalsExtractData($object): string
    {
        $owner = $this->getOwner();
        $badTypes = $owner->getUnsearchableTypes();
        $unsetFields = $owner->getUnsearchableFields();
        $string = '';
        foreach (['db', 'has_one', 'belongs', 'has_many', 'many_many', 'belongs_many_many'] as $relType) {
            $fields = Config::inst()->get($object->ClassName, $relType);
            if (! empty($fields)) {
                foreach ($fields as $name => $type) {
                    if (! in_array($type, $badTypes, true) && ! in_array($name, $unsetFields, true)) {
                        if (0 === stripos($type, 'Enum')) {
                            continue;
                        }
                        $endValue = '';
                        switch ($relType) {
                            case 'db':
                                $endValue = $object->{$name};
                                $isList = false;

                                break;
                            case 'belongs':
                            case 'has_one':
                                $values = $object->{$name}();
                                if ($values && $values->exists()) {
                                    $endValue = $values->getTitle();
                                } else {
                                    $endValue = '';
                                }
                                $isList = false;

                                break;
                            default:
                                $isList = true;
                        }
                        if ($isList) {
                            $listValues = [];
                            $values = $object->{$name}();
                            if ($values && $values->exists() && $values->count() < 13) {
                                foreach ($values as $item) {
                                    $listValues[] = $item->getTitle();
                                }
                            }
                            $endValue = implode('; ', array_filter($listValues));
                        }
                        $data = trim(strip_tags($endValue));
                        // $string .= $data ? $name . ': '.$data . '; ' : $name.' missing; ';
                        $string .= $data ? $data . '; ' : '';
                    }
                }
            }
        }

        return $string;
    }

    public function getUnsearchableFields() : array
    {
        $owner = $this->getOwner();
        $array = self::BASE_UNSEARCHABLE_FIELDS;
        if($owner->hasMethod('getUnsearchableFieldsExtras')) {
            $extraArray = $owner->getUnsearchableFieldsExtras();
        } else {
            $extraArray = Config::inst()->get(Page::class, 'unsearchable_fields_extra');
        }
        if(! empty($extraArray) && is_array($extraArray)) {
            $array = array_merge($array, $extraArray);
        }
        return $array;
    }

    public function getUnsearchableTypes() : array
    {
        $owner = $this->getOwner();
        $array = self::BASE_UNSEARCHABLE_TYPES;
        if($owner->hasMethod('getUnsearchableTypesExtras')) {
            $extraArray = $owner->getUnsearchableTypesExtras();
        } else {
            $extraArray = Config::inst()->get(Page::class, 'unsearchable_types_extra');
        }
        if(! empty($extraArray) && is_array($extraArray)) {
            $array = array_merge($array, $extraArray);
        }
        return $array;
    }

}
