## Description
This project provides integration with SugarCRM for Webform submissions. 
Module provides easy to use webform component mapping interface and SugarCRM configuration page. 
Each form component can be mapped to any field from all modules in respective SugarCRM system. 
On submission new record will be set in mapped CRM modules.

## Customization
As it is not possible for now to cover all options for SugarCRM specific module requirement, 
this module provides option to use the CRM integration class and extend with additional methods which could cover your 
specific requirements.

If the included submit handler does not provide all required for your specific needs functionality you can add your own 
with simply altering the form. It is important your submit handler to be executed before any of the Webform module 
submit handlers as for the mapping of the fields are used components machine names and these are replace with there CIDs 
after those submit handlers are executed.

## Features
* Integration with SugarCRM.
* Mapping interface for Webform components.
* Customizable.

## Requirements
* Webform
* Webform UI

**Note:** 
If You experiance difficulties with connecting to SugarCRM check CRM's '.htaccess' file for any restrction to REST 
service API.

### Configuration
* SugarCRM configuration: `/admin/config/services/sugarcrm`
* Webform components mapping: `admin/structure/webform/manage/{webform}/sugarcrm-mapping`

### Project Information
* Drupal project: https://www.drupal.org/project/webform_helptext
* Github project: https://github.com/nikolamitevffw/webform_helptext
