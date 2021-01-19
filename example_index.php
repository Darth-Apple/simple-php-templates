<?php
// License: GNU LGPL, Version 3. 
require("template.php");
$templates = new template_engine(“english”); 
$templates->load_lang(“core”); // Loads languages/english/core.lang.php

// Set some variables
$templates->set("title", "EXAMPLE TEMPLATE");

$people = array(
	array(“name” => “Steve”, “Age” => 23),
	array(“name” => “Emily”, “Age” => 21)
);
$templates->set(“people”, $people);

$templates->set("true", true);
$templates->set("false", false);

$templates->set("html_var", "<b><u><i>FORMATTED TEXT</i></u></b>");

$templates->render(“example”);