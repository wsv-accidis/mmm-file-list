<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

/*
Plugin Name: Mmm Simple File List (modified)
Description: Plugin to list files in a given directory.
Version: 2.5
Author: Adam Bissonnette, Wilhelm Svenselius
*/

class MM_FileList
{
    private $dateformat = "Y-m-d H:i:s";
    private $orderby = "name";
    private $order = "asc";
    private $prettyname = false;
    private $regexstrip = "";
    private $regexreplace = " ";
    private $regexfilter = "";
    private $regexfilterinclusive = false;
    private $removeextension = false;
    private $removesize = false;
    private $subdirs = false;
    private $typestolist = array();

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
                    "format" => "li",
                    "types" => "",
                    "class" => "",
                    "limit" => "-1",
                    "orderby" => "name", // name or date
                    "order" => "asc",
                    "target" => "",
                    "prettyname" => false,
                    "regexstrip" => "",
                    "regexreplace" => " ",
                    "regexfilter" => "",
                    "regexfilterinclusive" => false,
                    "dateformat" => "Y-m-d H:i:s",
                    "headings" => "",
                    "removesize" => false,
                    "removeextension" => false,
                    "subdirs" => false,
                    "usecwd" => false,
                ],
                $atts
            )
        );

        $this->dateformat = $dateformat;
        $this->orderby = $orderby;
        $this->order = $order;
        $this->prettyname = $prettyname;
        $this->regexstrip = $regexstrip;
        $this->regexreplace = $regexreplace;
        $this->regexfilter = $regexfilter;
        $this->regexfilterinclusive = $regexfilterinclusive;
        $this->removeextension = $removeextension;
        $this->removesize = $removesize;
        $this->subdirs = $subdirs;
        $this->typestolist = array_filter(explode(",", $types));

        $folder = $this->_trim_slashes($folder);

        $baseDir = wp_upload_dir();
        $sourceDir = $baseDir["path"] . "/" . $folder;
        $targetUrl = $baseDir["url"] . "/" . $folder;

        if ($usecwd) {
            $sourceDir = getcwd() . "/" . $folder;
            $targetUrl = $folder;
        }

        $output = "";
        $files = is_dir($sourceDir);

        if (!$files) {
            $output .= sprintf(
                '<div class="mmm-warning">The folder "%s" was not found at: "%s".',
                $sourceDir,
                $targetUrl
            );
        } else {
            $files = $this->_scan_directory($sourceDir);

            $list = $this->_get_list_of_files($files, $sourceDir, $targetUrl);

            if (is_numeric($limit)) {
                if ($limit > 0) {
                    $list = array_slice($list, 0, $limit);
                }
            }

            if ($target != "") {
                $target = 'target="' . $target . '"';
            }

            if (count($list) == 0) {
                $output .= sprintf(
                    '<div class="mmm-warning">No files (of extension(s): "%s") found in: %s </div>',
                    $types,
                    $targetUrl
                );
            } else {
                $formatAtts = ["class" => $class, "target" => $target];

                $size = '<span class="filesize"> ({size})</span>';
                $titlesize = "({size})";
                if ($this->removesize != false) {
                    $size = "";
                    $titlesize = "";
                }

                switch ($format) {
                    case "li":
                        $output = $this->_make_unordered_list(
                            $list,
                            $content,
                            $formatAtts
                        );
                        break;
                    case "li2":
                        $output = $this->_make_unordered_list(
                            $list,
                            "<a href=\"{url}\"{target}><span class=\"filename\">{name}</span>{$size} <span class=\"dateModified\">{date}</span> <span class=\"extension mm-{ext}\">{ext}</span></a>",
                            $formatAtts
                        );
                        break;
                    case "img":
                        $listTemplate = '<ul class="%s">%s</ul>';

                        if ($content == "") {
                            $content = $this->_add_file_atts_to_template(
                                "<a href=\"{url}\"{target}><img src=\"{url}\" class=\"{class}\" title=\"{name} {$titlesize}\" /></a>",
                                $formatAtts
                            );
                        }

                        $output = $this->_make_unordered_list(
                            $list,
                            $content,
                            $formatAtts
                        );
                        break;
                    case "custom":
                        $output = $this->_output_list(
                            $list,
                            $content,
                            $formatAtts
                        );
                        break;
                    case "table":
                        $output = $this->_make_tabular_list(
                            $list,
                            $content,
                            $formatAtts,
                            $headings
                        );
                        break;
                    case "comma":
                        $output = $this->_make_comma_delimited_list($list);
                        break;
                    default:
                        $output = $this->_make_unordered_list(
                            $list,
                            $content,
                            $formatAtts
                        );
                        break;
                }
            }
        }

        return $output;
    }

    function _scan_directory($sourceDir) 
    {
        $files = scandir($sourceDir);

        if ($this->orderby == "date") {
            $files = array_reverse(
                $this->_sort_files_by_date($sourceDir . "/", $files)
            );
        }

        if ($this->order == "desc") {
            $files = array_reverse($files);
        }

        if ($this->regexfilter != "") {
            $filteredfiles = [];

            foreach ($files as $file) {
                $filterMatch = preg_match($this->regexfilter, $file) == 1;
                if ($filterMatch && $this->regexfilterinclusive != false) {
                    array_push($filteredfiles, $file);
                } elseif (!$filterMatch && $this->regexfilterinclusive == false) {
                    array_push($filteredfiles, $file);
                }
            }
            $files = $filteredfiles;
        }

        return $files;
    }

    function _get_list_of_files($files, $sourceDir, $targetUrl, $subDir = "")
    {
        $list = [];

        foreach ($files as $file) {
            $path_parts = pathinfo($file);
            
            if (isset($path_parts["extension"])) {
                // File is a file, not a directory - evaluate and add to list

                $extension = $path_parts["extension"];

                if ($file != "." && $file != "..") {
                    if (!is_dir($sourceDir . "/" . $file)) {
                        $filename = $file;
                        if ($this->prettyname != false) {
                            $filename = $this->_regex_remove(
                                $file,
                                '/-|_| |\.[a-zA-Z0-9]*$/'
                            );
                        } elseif ($this->removeextension != false) {
                            $filename = $this->_regex_remove(
                                $file,
                                '/\.[a-zA-Z0-9]*$/'
                            );
                        }

                        if ($this->regexstrip != "") {
                            $filename = $this->_regex_remove(
                                $filename,
                                $this->regexstrip,
                                $this->regexreplace
                            );
                        }

                        $modifiedDate = date(
                            $this->dateformat,
                            filemtime($sourceDir . "/" . $file)
                        );

                        $file = [
                            "name" => $filename,
                            "ext" => $extension,
                            "date" => $modifiedDate,
                            "url" => $targetUrl . "/" . $file,
                            "size" => $this->_format_filesize(
                                filesize($sourceDir . "/" . $file)
                            ),
                            "subdir" => $subDir
                        ];

                        if (count($this->typestolist) > 0) {
                            if (in_array($extension, $this->typestolist)) {
                                array_push($list, $file);
                            }
                        } else {
                            array_push($list, $file);
                        }
                    }
                }
            } else if($this->subdirs && $file != "." && $file != ".." && $subDir == "") {
                // File is a directory, recurse (only one level)

                $subSourceDir = $sourceDir . "/" . $file;
                $subTargetUrl = $targetUrl . "/" . $file;

                $subDirFiles = $this->_scan_directory($subSourceDir);

                $subDirList = $this->_get_list_of_files(
                    $subDirFiles,
                    $subSourceDir,
                    $subTargetUrl,
                    $file
                );

                $list = array_merge($list, $subDirList);
            }
        }

        return $list;
    }

    function _add_file_atts_to_template($template, $fileAtts)
    {
        $output = $template;

        foreach ($fileAtts as $key => $value) {
            if (isset($value)) {
                $output = str_replace(
                    sprintf("{%s}", $key),
                    $value,
                    $output
                );
            }
        }

        return $output;
    }

    function _output_list($list, $content, $atts, $wrapper = "")
    {
        $listItemTemplate = $content;
        $items = "";

        // item == filename, value == path
        foreach ($list as $file => $fileatts) {
            $items .= $this->_add_file_atts_to_template(
                $listItemTemplate,
                $fileatts
            );
        }

        if ($wrapper != "") {
            return sprintf($wrapper, $atts["class"], $items);
        } else {
            return $items;
        }
    }

    function _make_comma_delimited_list($list)
    {
        $formattedList = [];
        foreach ($list as $entry) {
            array_push($formattedList, $entry["url"]);
        }
        return implode(",", $formattedList);
    }

    function _make_unordered_list($list, $content, $atts)
    {
        $size = '<span class=\"filesize\"> (%s)</span>';
        if ($this->removesize != false) {
            $size = "";
        }

        $listTemplate = '<ul class="%s">%s</ul>';
        $listItemTemplate = sprintf("<li>%s</li>", $content);

        if ($content == "") {
            $content = "<a href=\"%s\"%s><span class=\"filename\">%s</span>{$size}</a>";
            $listItemTemplate = sprintf("<li>%s</li>", $content);

            $items = "";

            // item == filename, value == path
            foreach ($list as $file => $fileatts) {
                $items .= sprintf(
                    $listItemTemplate,
                    $fileatts["url"],
                    $atts["target"],
                    $fileatts["name"],
                    $fileatts["size"]
                );
            }

            return sprintf($listTemplate, $atts["class"], $items);
        } else {
            return $this->_output_list(
                $list,
                $listItemTemplate,
                $atts,
                $listTemplate
            );
        }
    }

    function _make_tabular_list($list, $content, $atts, $headings)
    {
        $sizeth = '<th class="filesize">Size</th>';
        $sizetd = '<td class="filesize">%s</td>';
        if ($this->removesize != false) {
            $sizeth = "";
            $sizetd = "";
        }

        $listTemplate = '<table class="%s">%s%s</table>';
        $listHeadingTemplate = "<tr><th class=\"filename\">Filename / Link</th>{$sizeth}</tr>";
        $rowWrapper = "<tr>%s</tr>";

        if ($headings != "") {
            $headingWrapper = "<th>%s</th>";

            $headingList = explode(",", $headings);
            $output = "";

            foreach ($headingList as $heading) {
                $output .= sprintf($headingWrapper, $heading);
            }

            $listHeadingTemplate = sprintf($rowWrapper, $output);
        }

        if ($content == "") {
            $listItemTemplate = "<tr><td class=\"filename\"><a href=\"%s\"%s>%s</a></td>{$sizetd}</tr>";

            $items = "";

            foreach ($list as $filename => $fileatts) {
                $items .= sprintf(
                    $listItemTemplate,
                    $fileatts["url"],
                    $atts["target"],
                    $fileatts["name"],
                    $fileatts["size"]
                );
            }
        } else {
            $items = $this->_output_list(
                $list,
                sprintf($rowWrapper, $content),
                $atts
            );
        }

        return sprintf(
            $listTemplate,
            $atts["class"],
            $listHeadingTemplate,
            $items
        );
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

    function _sort_files_by_date($sourceDir, $files)
    {
        $arr = [];
        $i = 0;
        foreach ($files as $filename) {
            if ($filename != "." && $filename != "..") {
                if (filemtime($sourceDir . $filename) === false) {
                    return false;
                }
                $dat = date("YmdHis", filemtime($sourceDir . $filename));
                $arr[$dat . "," . $i++] = $filename;
            }
        }
        if (!ksort($arr)) {
            return false;
        }
        return $arr;
    }

    function _trim_slashes($folder)
    {
        $fixedPath = rtrim($folder, "/");
        $fixedPath = ltrim($fixedPath, "/");
        return $fixedPath;
    }

    function _flip_slahes($folder)
    {
        return str_replace("/", "\\", $folder);
    }

    function _regex_remove($str = "", $regex, $replacememnt = " ")
    {
        $output = preg_replace($regex, $replacememnt, $str);
        $output = preg_replace("/(?<!\ )[A-Z]/", ' $0', $output);
        return trim($output);
    }

    function _pretty_filename($str = "")
    {
        $regex = '/-|_| |\.[a-z0-9]*$/';
        $output = preg_replace($regex, " ", $str);
        $output = preg_replace("/(?<!\ )[A-Z]/", ' $0', $output);
        return trim($output);
    }

    function _strip_empty_paragraphs($string)
    {
        $patterns = ["#^\s*</p>#", '#<p>\s*$#'];
        return preg_replace($patterns, "", $string);
    }
}

add_action("init", "MM_FileList_Init", 5);
function MM_FileList_Init()
{
    global $MM_FileList;
    $MM_FileList = new MM_FileList();
}
?>
