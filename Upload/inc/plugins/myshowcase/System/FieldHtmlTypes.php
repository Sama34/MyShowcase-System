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

namespace MyShowcase\System;

class FieldHtmlTypes
{
    public const TextArea = 'textarea';
    public const SelectSingle = 'db';
    //public const SelectMultiple = 0;
    //public const Button = 0;
    public const CheckBox = 'checkbox';
    //public const Color = 0;
    public const Date = 'date';
    //public const DateTimeLocal = 0;
    //public const Email = 0;
    //public const Hidden = 0;
    //public const Month = 0;
    //public const Number = 0;
    //public const Password = 0;
    public const Radio = 'radio';
    //public const Search = 0; // select 2 users search ?
    //public const Telephone = 0;
    public const Text = 'textbox';
    //public const Time = 0;
    public const Url = 'url';
    //public const Week = 0;
}