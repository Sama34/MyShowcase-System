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

trait FieldTrait
{
    public function setUserValue(string $userValue): FieldsInterface
    {
        $this->entryFieldValue = $userValue;

        return $this;
    }

    public function getUserValue(): string
    {
        return $this->entryFieldValue ?? '';
    }

    public function getFieldHeader(): string
    {
        global $lang;

        return $this->fieldData['field_label'] ?? ($lang->{'myshowcase_field_' . $this->fieldData['field_key']} ?? '');
    }

    public function getFieldDescription(): string
    {
        global $lang;

        $fieldDescription = $this->fieldData['description'] ?? ($lang->{'myshowcase_field_' . $this->fieldData['field_key'] . 'Description'} ?? '');

        if ($fieldDescription) {
            if ($this->fieldData['allow_multiple_values']) {
                // todo, add description for comma/line multiple separator
            }

            $fieldDescription = eval(
            $this->showcaseObject->renderObject->templateGet(
                $this->templatePrefixCreateUpdate . 'Description'
            )
            );
        }

        return $fieldDescription;
    }

    public function fieldAcceptsMultipleValues(): bool
    {
        return /*\MyShowcase\Core\fieldTypeMatchText($this->fieldData['field_type']) &&*/ $this->fieldData['allow_multiple_values'];
    }

    public function renderMain(): string
    {
        if (!$this->fieldData['display_in_main_page'] || !is_member($this->fieldData['allowed_groups_view'])) {
            return '';
        }
    }

    public function renderEntry(): string
    {
        if (!$this->fieldData['display_in_view_page'] || !is_member($this->fieldData['allowed_groups_view'])) {
            return '';
        }

        $userValue = $this->getUserValue();

        if ($userValue) {
            $fieldHeader = $this->getFieldHeader();

            if ($this->fieldAcceptsMultipleValues()) {
                $userValues = explode($this->multipleSeparator, $userValue);
            } else {
                $userValues = [$userValue];
            }

            foreach ($userValues as &$userValue) {
                if ($this->fieldData['parse'] || $this->showcaseObject->renderObject->highlightTerms) {
                    $userValue = $this->showcaseObject->parseMessage(
                        $userValue,
                        $this->showcaseObject->parserOptions
                    );
                } else {
                    $userValue = htmlspecialchars_uni($userValue);
                }

                $userValue = eval(
                $this->showcaseObject->renderObject->templateGet(
                    $this->templatePrefixEntry . $this->templateName . 'Value'
                )
                );
            }

            $userValues = implode($this->multipleConcatenator, $userValues);

            return eval(
            $this->showcaseObject->renderObject->templateGet(
                $this->templatePrefixEntry . $this->templateName
            )
            );
        } elseif ($this->showcaseObject->config['display_empty_fields']) {
            global $lang;

            return $lang->myShowcaseEntryFieldValueEmpty;
        }

        return '';
    }
}