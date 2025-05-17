<?php
/**
 * MyShowcase Plugin for MyBB - Main Plugin
 * Copyright 2012 CommunityPlugins.com, All Rights Reserved
 *
 * Website: https://github.com/Sama34/MyShowcase-System
 * Version 2.5.2
 * License: Creative Commons Attribution-NonCommerical ShareAlike 3.0
 * http://creativecommons.org/licenses/by-nc-sa/3.0/legalcode
 * File: \inc\plugins\myshowcase.php
 *
 */

namespace MyShowcase\Fields;

use MyBB;
use MyShowcase\System\FieldHtmlTypes;

use function MyShowcase\Core\cacheGet;
use function MyShowcase\Core\fieldDataGet;
use function MyShowcase\Core\fieldTypeMatchText;

use const MyShowcase\Core\CACHE_TYPE_ATTACHMENT_TYPES;

trait MultipleFieldTrait
{
    public function renderCreateUpdate(string $alternativeBackground, int $fieldTabIndex): string
    {
        global $mybb;

        $userValue = $this->getUserValue();

        $fieldHeader = $this->getFieldHeader();

        $fieldDescription = $this->getFieldDescription();

        $inputName = $this->fieldData['field_key'] . '[]';

        if ($this->fieldAcceptsMultipleValues()) {
            $fieldItems = explode($this->multipleSeparator, $userValue);
        } else {
            $fieldItems = [$userValue];
        }

        $inputID = $this->fieldData['field_key'] . '_input';

        $inputValues = $mybb->get_input($this->fieldData['field_key'], MyBB::INPUT_ARRAY);

        $requiredElement = '';

        if ($this->fieldData['is_required']) {
            $requiredElement = 'required="required"';
        }

        $fieldID = (int)$this->fieldData['field_id'];

        switch ($this->fieldData['html_type']) {
            case FieldHtmlTypes::CheckBox:
            case FieldHtmlTypes::Radio:
            case FieldHtmlTypes::Select:
                $fieldDataObjects = fieldDataGet(
                    ["field_id='{$fieldID}'"],
                    ['value'],
                    ['order_by' => 'display_order', 'allowed_groups_fill']
                );
        }

        if (empty($fieldDataObjects)) {
            return '';
        }

        $fieldItems = [];

        foreach ($fieldDataObjects as $fieldDataID => $fieldDataData) {
            if (!empty($fieldDataData['allowed_groups_fill']) && !is_member($fieldDataData['allowed_groups_fill'])) {
                continue;
            }

            $valueIdentifier = $fieldDataID;

            $valueName = htmlspecialchars_uni($fieldDataData['value']);

            $checkedElement = $selectedElement = '';

            if (!empty($inputValues[$this->fieldData['field_key']])) {
                $checkedElement = 'checked="checked"';

                $selectedElement = 'selected="selected"';
            }

            // todo, check box can be required per check box

            $fieldItems[] = eval(
            $this->showcaseObject->renderObject->templateGet(
                $this->templatePrefixCreateUpdate . $this->templateName . 'Item'
            )
            );
        }

        /*
        foreach ($fieldItems as &$userValue) {
            if ($this->fieldData['parse'] || $this->showcaseObject->renderObject->highlightTerms) {
                $userValue = $this->showcaseObject->parseMessage(
                    $userValue,
                    $this->showcaseObject->parserOptions
                );
            } else {
                $userValue = htmlspecialchars_uni($userValue);
            }

            $valueIdentifier = 1;

            $valueName = htmlspecialchars_uni($this->fieldData['field_key']);

            $checkedElement = $selectedElement = '';

            if (!empty($inputValues[$this->fieldData['field_key']])) {
                $checkedElement = 'checked="checked"';

                $selectedElement = 'selected="selected"';
            }

            // todo, check box can be required per check box

            $userValue = eval(
            $this->showcaseObject->renderObject->templateGet(
                $this->templatePrefixCreateUpdate . $this->templateName . 'Item'
            )
            );
        }*/

        $fieldItems = implode('', $fieldItems);

        $fieldPlaceholder = htmlspecialchars_uni($this->fieldData['placeholder']);

        $fieldItems = eval(
        $this->showcaseObject->renderObject->templateGet(
            $this->templatePrefixCreateUpdate . $this->templateName
        )
        );

        $acceptElement = '';

        return eval($this->showcaseObject->renderObject->templateGet($this->templatePrefixCreateUpdate));
    }
}