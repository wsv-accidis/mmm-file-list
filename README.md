# MMM Simple File List Plugin

Plugin based on [**mmm-file-list**](https://wordpress.org/plugins/mmm-file-list/) by [Adam Bissonnette](https://mediamanifesto.com/).

Licensed under the [GNU General Public License version 2 or later](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html).

This plugin displays a very simple list of files from a given directory, with download links, using the shortcode ```[MMFileList /]```. By providing the path to a folder, any folder to which Wordpress has access can be listed.

*NOTE: This can potentially expose files in any folder in the system. This is only a problem if unauthorized users can manipulate the parameters passed to the shortcode - so make sure they can't do that. [See details here.](https://research.cleantalk.org/cve-2023-4297-mmm-simple-file-list/)*

Please see ```readme.original``` for the documentation that was originally distributed with this plugin, in case the original source is lost. That this includes descriptions of functionality that has been deliberately removed. If you want the old version and it's no longer available from the original source, fetch the first commit in this repo.

## Usage

Example:

```[MMFileList folder="../path/goes/here" /]```

The following arguments are supported:

* ```folder``` Source folder, relative to the base uploads folder of your Wordpress install.
* ```class``` (optional) CSS class that will be applied to the ```<ul>``` element, for styling the list.
* ```target``` (optional) Link target that will be applied to download links.
* ```limit``` (optional) Maximum number of files to list.
* ```subdirs``` (optional) If true, recurse into one level of subdirectories.

## Modifications

This version was modified by [Wilhelm Svenselius](https://accidis.se) for my personal use.

### 2026-11-05

* The listing traverses into one level of subdirectories if ```subdirs``` is ```true```.
* Removed all functionality except basic unordered list, and simplified the format.
* Code cleanup and light refactoring for maintainability.
