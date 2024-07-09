# Profile Compilation Module

## Overview

The Profile Compilation module is a custom Drupal module designed to streamline the process of creating and updating user profiles and managing profile images. It provides functionality for importing user data from CSV files and uploading profile pictures in bulk.

## Features

1. User Profile Creation and Update
  - Import user profiles from CSV files
  - Automatically create new user accounts or update existing ones
  - Set user biographies and other profile information

2. Profile Image Management
  - Bulk upload profile images via ZIP file
  - Automatically associate images with user accounts

## Installation

1. Download the module and place it in your Drupal installation's `modules/custom` directory.
2. Enable the module through the Drupal admin interface or using Drush:
   drush en profile_comp

## Usage

### Importing User Profiles

1. Navigate to Content > Compile Profiles
2. Use the "Profile Creation Form" to upload a CSV file containing user data
3. Select the appropriate delimiter and click "Import"

#### CSV Format

The CSV should contain the following columns:
- First Name
- Last Name
- Email
- URL
- Bio

Example:
First Name,Last Name,Email,URL,Bio
John,Doe,john.doe@example.com,https://example.com/john,John's biography...
Jane,Smith,jane.smith@example.com,https://example.com/jane,Jane's biography...

#### User Matching Algorithm

The module uses the following algorithm to match CSV entries to existing users:

1. Normalize the CSV name and existing usernames by:
  - Removing non-alphanumeric characters (except spaces)
  - Replacing multiple spaces with a single space
  - Converting to lowercase and trimming

2. Check if the normalized CSV name is contained within the normalized username
3. If not found, check if all parts of the CSV name are in the username

This approach allows for matching users even if there are slight variations in naming conventions or additional information in the username.

All users are currently loaded before matching begins with each CSV entry.

#### Points of Concern

- Email: Currently set as 'cps-vo-test-' + CSV email. This is a placeholder to avoid sending actual emails.
- Password: Currently set to 'bob'. This is a placeholder.
- URL: Currently added to the beginning of the biography field.

### Uploading Profile Images

1. Navigate to Content > Compile Profiles
2. Use the "Image Creation Form" to upload a ZIP file containing profile images
3. Ensure images are named in the format "First_Last.jpg" (or .jpeg, .png, .gif)
4. Choose whether to overwrite existing profile pictures
5. Click "Upload" to process the images

## Module Structure

* `profile_comp.info.yml`: Module definition file
* `profile_comp.links.menu.yml`: Defines admin menu links
* `profile_comp.routing.yml`: Defines routes for the module's pages and forms
* `profile_comp.services.yml`: Defines services used by the module
* `src/Form/ProfileCreationForm.php`: Handles CSV import and user creation/update
* `src/Form/ImageCreationForm.php`: Handles bulk image upload and association
* `src/Parser.php`: Handles CSV parsing functionality

## Customization

To customize the module's functionality:

1. Modify form classes in `src/Form/` to change form fields or behavior
2. Adjust the user matching algorithm in `ProfileCreationForm.php` if needed
3. Update the image processing logic in `ImageCreationForm.php` if required
4. Modify service definitions in `profile_comp.services.yml` to alter module services

## Troubleshooting

* If CSV imports fail, check that the CSV format matches the expected structure
* For image upload issues, ensure that image filenames match the "First_Last.jpg" format
* If users are not being matched correctly, review the normalization and matching logic in `ProfileCreationForm.php`
* Check Drupal logs for any error messages or warnings related to the module

## Dependencies

This module requires:
* Drupal Core 9.4 or higher
* Entity API
* File module
* User module

Ensure these dependencies are met before enabling the module.

## Contributing

Contributions to the Profile Compilation module are welcome. Please submit issues and pull requests
