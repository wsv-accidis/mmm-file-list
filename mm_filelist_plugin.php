<?php
/*
Plugin Name: Mmm Simple File List (modified)
Description: Plugin to list files in a given directory.
Version: 2.5
Author: Adam Bissonnette, Wilhelm Svenselius
*/

//error_reporting(E_ALL);
//ini_set('display_errors', 1);

class MM_FileList
{
    function __construct($config = [])
    {
        add_shortcode("MMFileList", [&$this, "MMFileList"]);
    }

    function MMFileList($atts, $content = "")
    {
        $content = $this->_strip_empty_paragraphs($content);

        extract(
            shortcode_atts(
                [
                    "folder" => "",
                    "class" => "",
                    "limit" => "-1",
                    "target" => "",
                    "subdirs" => false
                ],
                $atts
            )
        );

        $baseDir = wp_upload_dir();
        $folder = $this->_trim_slashes($folder);
        $sourceDir = $baseDir["path"] . "/" . $folder;
        $targetUrl = $baseDir["url"] . "/" . $folder;

        if (!is_dir($sourceDir)) {
            /*
             * Error: Folder not found or path invalid.
             */
            return sprintf('<div class="mmm-warning">The folder was not found: &quot;%s&quot;</div>', $sourceDir);            
        } else {
            $list = $this->_get_list_of_files($sourceDir, $targetUrl, "", $subdirs);

            if (is_numeric($limit) && $limit > 0) {
                $list = array_slice($list, 0, $limit);                
            }

            if (count($list) == 0) {
                /*
                 * Error: No files found in the folder.
                 */
                return sprintf('<div class="mmm-warning">No files found in: &quot;%s&quot;</div>', $sourceDir);
            } else {
                /*
                 * Generate the list.
                 */
                return $this->_make_unordered_list($list, $subdirs, $class, $target);
            }
        }
    }

    function _get_list_of_files($sourceDir, $targetUrl, $subDir, $subdirs)
    {
        $files = scandir($sourceDir);
        $list = [];

        foreach ($files as $file) {
            if($file == "." || $file == "..") {
                continue;
            }

            $filePath = $sourceDir . "/" . $file;
            if (!is_dir($filePath)) {
                // File is a file, not a directory - evaluate and add to list
                   
                $fileDetails = [
                    "name" => $file,
                    "url" => $targetUrl . "/" . $file,
                    "size" => $this->_format_filesize(
                        filesize($sourceDir . "/" . $file)
                    ),
                    "subdir" => $subDir
                ];

                array_push($list, $fileDetails);
            } if($subdirs && $subDir == "") {
                // File is a directory, recurse (only one level)

                $subSourceDir = $sourceDir . "/" . $file;
                $subTargetUrl = $targetUrl . "/" . $file;

                $subDirList = $this->_get_list_of_files(
                    $subSourceDir,
                    $subTargetUrl,
                    $file,
                    $subdirs
                );

                $list = array_merge($list, $subDirList);
            }
        }

        return $list;
    }

    function _make_unordered_list($list, $subdirs, $class, $target)
    {
        if ($target != "") {
            $target = 'target="' . $target . '"';
        }

        $listTemplate = '<ul class="%s">%s</ul>';
        $listItemTemplate = '<li><a href="%s"%s"><span class="filename">%s</span></a> <span class="filesize">(%s)</span></li>';
        $subdirTemplate = '<h3>%s</h3>';
        
        $buffer = "";
        $items = "";
        $lastsubdir = "";
        
        foreach ($list as $file => $fileatts) {
            if($subdirs && $fileatts["subdir"] != $lastsubdir) {
                if($items != "") {
                    $buffer .= sprintf($listTemplate, $class, $items);
                    $items = "";
                }
                $buffer .= sprintf($subdirTemplate, $fileatts["subdir"]);
                $lastsubdir = $fileatts["subdir"];                        
            }

            $items .= sprintf(
                $listItemTemplate,
                $fileatts["url"],
                $target,
                $fileatts["name"],
                $fileatts["size"]
            );
        }

        if($items != "") {
            $buffer .= sprintf($listTemplate, $class, $items);
        }

        return $buffer;            
}

    function _format_filesize($bytes)
    {
        $bytes = floatval($bytes);
        $arBytes = [
            [
                "UNIT" => "Pb",
                "VALUE" => pow(1024, 5),
            ],
            [
                "UNIT" => "Tb",
                "VALUE" => pow(1024, 4),
            ],
            [
                "UNIT" => "Gb",
                "VALUE" => pow(1024, 3),
            ],
            [
                "UNIT" => "Mb",
                "VALUE" => pow(1024, 2),
            ],
            [
                "UNIT" => "Kb",
                "VALUE" => 1024,
            ],
            [
                "UNIT" => "Bytes",
                "VALUE" => 1,
            ],
        ];

        foreach ($arBytes as $arItem) {
            if ($bytes >= $arItem["VALUE"]) {
                $result = $bytes / $arItem["VALUE"];
                $result =
                    str_replace(",", ".", strval(round($result, 2))) .
                    " " .
                    $arItem["UNIT"];
                break;
            }
        }
        return $result;
    }

    function _strip_empty_paragraphs($string)
    {
        $patterns = ["#^\s*</p>#", '#<p>\s*$#'];
        return preg_replace($patterns, "", $string);
    }

    function _trim_slashes($folder)
    {
        $fixedPath = rtrim($folder, "/");
        $fixedPath = ltrim($fixedPath, "/");
        return $fixedPath;
    }
}

add_action("init", "MM_FileList_Init", 5);
function MM_FileList_Init()
{
    global $MM_FileList;
    $MM_FileList = new MM_FileList();
}
?>
