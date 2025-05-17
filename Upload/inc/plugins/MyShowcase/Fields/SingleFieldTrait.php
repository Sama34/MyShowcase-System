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

use MyShowcase\System\FieldHtmlTypes;

use function MyShowcase\Core\cacheGet;
use function MyShowcase\Core\fieldTypeMatchText;

use const MyShowcase\Core\CACHE_TYPE_ATTACHMENT_TYPES;

trait SingleFieldTrait
{
    public function renderCreateUpdate(string $alternativeBackground, int $fieldTabIndex): string
    {
        if (!$this->fieldData['display_in_create_update_page'] || !is_member($this->fieldData['allowed_groups_fill'])) {
            return '';
        }

        global $mybb, $lang;

        $inputName = $this->fieldData['field_key'];

        $inputID = $this->fieldData['field_key'] . '_input';

        $userValue = htmlspecialchars_uni($mybb->get_input($this->fieldData['field_key']));

        $fieldHeader = $this->getFieldHeader();

        $fieldDescription = $this->getFieldDescription();

        $patternElement = '';

        if ($this->fieldData['regular_expression']) {
            $patternElement = 'pattern="' . $this->fieldData['regular_expression'] . '"';
        }

        $defaultValue = '';

        if ($this->fieldData['default_value']) {
            $defaultValue = strip_tags($this->fieldData['default_value']);
        }

        $requiredElement = '';

        if ($this->fieldData['is_required']) {
            $requiredElement = 'required="required"';
        }

        $editorCodeButtons = $editorSmilesInserter = '';

        if (in_array($this->fieldData['html_type'], [
            FieldHtmlTypes::Text,
            FieldHtmlTypes::Search,
            FieldHtmlTypes::TextArea,
        ])) {
            if ($this->fieldData['enable_editor']) {
                $this->showcaseObject->renderObject->buildEditor(
                    $editorCodeButtons,
                    $editorSmilesInserter,
                    $inputID
                );
            }
        }

        $acceptElement = $captureElement = '';

        if ($this->fieldData['html_type'] === FieldHtmlTypes::File) {
            $attachmentTypes = cacheGet(CACHE_TYPE_ATTACHMENT_TYPES)[$this->showcaseObject->showcase_id] ?? [];

            $acceptElement = implode(
                ',',
                array_merge(
                    array_column($attachmentTypes, 'mime_type'),
                    array_column($attachmentTypes, 'file_extension')
                )
            );

            $acceptElement = "accept=\"{$acceptElement}\"";

            switch ($this->fieldData['file_capture']) {
                case 1:
                    $captureElement = 'capture="user"';

                    break;
                case 2:
                    $captureElement = 'capture="environment"';

                    break;
            }
        }

        $inputSize = 40;

        if ($this->fieldData['html_type'] === FieldHtmlTypes::Select) {
            $inputSize = 5;
        }

        $minimumLength = $maximumLength = '';

        if (in_array($this->fieldData['html_type'], [
            FieldHtmlTypes::Date,
            FieldHtmlTypes::Month,
            FieldHtmlTypes::Week,
            FieldHtmlTypes::Time,
            FieldHtmlTypes::DateTimeLocal,
            FieldHtmlTypes::Number,
            FieldHtmlTypes::Range,
        ])) {
            $minimumLength = (int)$this->fieldData['minimum_length'];

            $maximumLength = (int)$this->fieldData['maximum_length'];
        }

        $minValue = $maxValue = '';

        if (in_array($this->fieldData['html_type'], [
            FieldHtmlTypes::Date,
            FieldHtmlTypes::Month,
            FieldHtmlTypes::Week,
            FieldHtmlTypes::Time,
            FieldHtmlTypes::DateTimeLocal,
            FieldHtmlTypes::Number,
            FieldHtmlTypes::Range,
        ])) {
            $minValue = (int)$this->fieldData['minimum_length'];

            $maxValue = (int)$this->fieldData['maximum_length'];
        }

        $fieldPlaceholder = htmlspecialchars_uni($this->fieldData['placeholder']);

        if (!empty($this->fieldData['regular_expression'])) {
            $patternElement = 'pattern="' . $this->fieldData['regular_expression'] . '"';
        }

        $fieldItems = eval(
        $this->showcaseObject->renderObject->templateGet(
            $this->templatePrefixCreateUpdate . $this->templateName
        )
        );

        return eval($this->showcaseObject->renderObject->templateGet($this->templatePrefixCreateUpdate));
    }
}