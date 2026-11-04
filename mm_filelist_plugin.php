<?php
/*
Plugin Name: Mmm Simple File List
Plugin URI: http://www.mediamanifesto.com
Description: Plugin to list files in a given directory using this shortcode [MMFileList folder="optional starting from base uploads path" format="li (unordered list) or table (tabular) or img (unordered list of images) or comma (plain text, comma, delimited) types="optional file-extension e.g. pdf,doc" class="optional css class for html list"]
Version: 2.3
Author: Adam Bissonnette
Author URI: http://www.mediamanifesto.com
*/

class MM_FileList
{
    public static $attsKeyTemplate = "{%s}";
    private $removeextension = false;
    private $removesize = false;

    function __construct( $config = array() ) {
        add_shortcode( 'MMFileList', array(&$this, 'ListFiles') );
    }
    
    function copter_remove_crappy_markup( $string )
    {
        $patterns = array(
            '#^\s*</p>#',
            '#<p>\s*$#'
        );

        return preg_replace($patterns, '', $string);
    }

    function ListFiles($atts, $content="")
    {   
        //Strip any empty <p> tags
        //Credit goes to: https://gist.github.com/jlengstorf/5370457
        $content = $this->copter_remove_crappy_markup($content);

        extract( shortcode_atts( array(
        'folder' => '',
        'format' => 'li',
        'types' => '',
        'class' => '',
        'limit' => '-1',
        'orderby' => 'name', //name or date
        'order' => "asc",
        'target' => '',
        'prettyname' => false,
        'regexstrip' => '',
        'regexreplace' => ' ',
        'regexfilter' => '',
        'regexfilterinclusive' => false,
        'dateformat' => 'Y-m-d H:i:s',
        'headings' => '',
        'removesize' => false,
        'removeextension' => false,
        'usecwd' => false
        ), $atts ) );
        
        $this->removesize = $removesize;
        $this->removeextension = $removeextension;

        $folder = $this->_check_for_slashes($folder);

        $baseDir = wp_upload_dir(); //Base Upload Directory
        $dir = $baseDir['path'] . '/' . $folder;
        $outputDir = $baseDir['url'] . '/' . $folder; //ex. http://example.com/wp-content/uploads/2010/05/../../cats

        if ($usecwd) {
            $dir = getcwd() . '/'.  $folder;
            $outputDir = $folder;
        }
        
        $typesToList = array_filter(explode(",", $types));

        $output = "";

        $files = is_dir($dir);

        if (!$files)
        {
            $output .= sprintf('<div class="mmm-warning">The folder "%s" was not found at: "%s".', $dir, $outputDir);
        }
        else
        {
            $files = scandir($dir);

            $list = array();

            if ($orderby == "date")
            {
                $files = array_reverse($this->rearrange_files_by_date($dir . "/", $files));
            }

            if ($order == "desc")
            {
                $files = array_reverse($files);
            }

			if ($regexfilter != "") {
                $filteredfiles = array();

                foreach($files as $file)
                {
					$filterMatch = preg_match($regexfilter, $file) == 1;
                    if ($filterMatch && $regexfilterinclusive != false) {
                        array_push($filteredfiles, $file);
                    }
                    elseif (!$filterMatch && $regexfilterinclusive == false) {
						array_push($filteredfiles, $file);
                    }
                }
                $files = $filteredfiles;
            }
			
            foreach($files as $file)
            {
                $path_parts = pathinfo($file);

                if (isset($path_parts['extension'])) //check for folders - don't list them
                {
                    $extension = $path_parts['extension'];
                    
                    if($file != '.' && $file != '..')
                    {
                        if(!is_dir($dir.'/'.$file))
                        {
                            $filename = $file;
                            if ($prettyname != false)
                            {
                                $filename = $this->_regex_remove($file, '/-|_| |\.[a-zA-Z0-9]*$/');
                            } elseif ($this->removeextension != false) {
                                $filename = $this->_regex_remove($file, '/\.[a-zA-Z0-9]*$/');
                            }

                            if ($regexstrip != "")
                            {
                                $filename = $this->_regex_remove($filename, $regexstrip, $regexreplace);
                            }

                            $modifiedDate = date($dateformat, filemtime($dir . "/" . $file));

                            $file = array("name" => $filename, "ext" => $extension, "date" => $modifiedDate, "url" => $outputDir . "/" . $file, "size" => $this->human_filesize(filesize($dir . '/' . $file)));
                            
                            //If we are looking for specific types then only list those types, otherwise list everything
                            if (count($typesToList) > 0)
                            {
                                if (in_array($extension, $typesToList))
                                {
                                    array_push($list, $file);
                                }
                            }
                            else
                            {
                                array_push($list, $file);
                            }
                        }
                    }
                }
            }
            
            if (is_numeric($limit))
            {
                if ($limit > 0)
                {
                    $list = array_slice($list, 0, $limit);
                }
            }

            if ($target != '')
            {
                $target = 'target="' . $target . '"';
            }

            if (count($list) == 0)
            {
                $output .= sprintf('<div class="mmm-warning">No files (of extension(s): "%s") found in: %s </div>', $types, $outputDir);
            }
            else
            {
                $formatAtts = array("class" => $class, "target" => $target);

                $size = '<span class="filesize"> ({size})</span>';
                $titlesize = '({size})';
                if ($this->removesize != false) {
                    $size = '';
                    $titlesize = '';
                }

                switch($format){
                    case 'li':
                        $output = $this->_MakeUnorderedList($list, $content, $formatAtts);
                        break;
                    case 'li2':
                        $output = $this->_MakeUnorderedList($list,
                                    "<a href=\"{url}\"{target}><span class=\"filename\">{name}</span>{$size} <span class=\"dateModified\">{date}</span> <span class=\"extension mm-{ext}\">{ext}</span></a>",
                                    $formatAtts);
                        break;
                    case 'img':
                        $listTemplate = '<ul class="%s">%s</ul>';

                        if ($content == "")
                        {
                            $content = $this->_AddFileAttsToTemplate("<a href=\"{url}\"{target}><img src=\"{url}\" class=\"{class}\" title=\"{name} {$titlesize}\" /></a>", $formatAtts);
                        }

                        $output = $this->_MakeUnorderedList($list, $content, $formatAtts);
                    break;
                    case 'custom':
                        $output = $this->_OutputList($list, $content, $formatAtts);
                    break;
                    case 'table':
                        $output = $this->_MakeTabularLIst($list, $content, $formatAtts, $headings);
                    break;
                    case 'comma':
                        $output = $this->_MakeCommaDelimitedList($list);
                    break;
                    default:
                        $output = $this->_MakeUnorderedList($list, $content, $formatAtts);
                    break;
                }
            }
        }
        
        return $output;
    }

    function _AddFileAttsToTemplate($template, $fileAtts)
    {
        $output = $template;

        foreach ($fileAtts as $key => $value) {
            if (isset($value))
            {
                $output = str_replace(sprintf(MM_FileList::$attsKeyTemplate, $key), $value, $output);
            }
        }

        return $output;
    }

    function _OutputList($list, $content, $atts, $wrapper="")
    {
        $listItemTemplate = $content;

        $items = "";

        foreach ($list as $file => $fileatts) //in this case item == filename, value == path
        {
            $items .= $this->_AddFileAttsToTemplate($listItemTemplate, $fileatts);
        }
        
        if ($wrapper != "")
        {
            return sprintf($wrapper, $atts["class"], $items);
        }
        else
        {
            return $items;
        }
    }

    function _MakeCommaDelimitedList($list)
    {
        $formattedList = array();

        foreach ($list as $entry) {
            array_push($formattedList, $entry["url"]);
        }

        return implode(",", $formattedList);
    }

    function _MakeUnorderedList($list, $content, $atts)
    {
        $size = '<span class=\"filesize\"> (%s)</span>';
        if ($this->removesize != false) {
            $size = '';
        }

        $listTemplate = '<ul class="%s">%s</ul>';
        $listItemTemplate = sprintf('<li>%s</li>', $content);

        if ($content == "")
        {
            $content = "<a href=\"%s\"%s><span class=\"filename\">%s</span>{$size}</a>";
            $listItemTemplate = sprintf('<li>%s</li>', $content);

            $items = "";
        
            foreach ($list as $file => $fileatts) //in this case item == filename, value == path
            {
                $items .= sprintf($listItemTemplate, $fileatts["url"], $atts["target"], $fileatts["name"], $fileatts["size"]);
            }
            
            return sprintf($listTemplate, $atts["class"], $items);
        }
        else
        {
            return $this->_OutputList($list, $listItemTemplate, $atts, $listTemplate);
        }
    }

    function _MakeTabularList($list, $content, $atts, $headings)
    {
        $sizeth = '<th class="filesize">Size</th>';
        $sizetd = '<td class="filesize">%s</td>';
        if ($this->removesize != false) {
            $sizeth = '';
            $sizetd = '';
        }

        $listTemplate = '<table class="%s">%s%s</table>';
        $listHeadingTemplate = "<tr><th class=\"filename\">Filename / Link</th>{$sizeth}</tr>";
        $rowWrapper = "<tr>%s</tr>";

        if ($headings != "")
        {
            $headingWrapper = "<th>%s</th>";

            $headingList = explode(",", $headings);
            $output = "";

            foreach ($headingList as $heading) {
                $output .= sprintf($headingWrapper, $heading);
            }

            $listHeadingTemplate = sprintf($rowWrapper, $output);
        }

        if ($content == "")
        {
            $listItemTemplate = "<tr><td class=\"filename\"><a href=\"%s\"%s>%s</a></td>{$sizetd}</tr>";

            $items = "";

            foreach ($list as $filename => $fileatts) {
                $items .= sprintf($listItemTemplate, $fileatts["url"], $atts["target"], $fileatts["name"], $fileatts["size"]);
            }
        }
        else
        {
            $items = $this->_OutputList($list, sprintf($rowWrapper, $content), $atts);
        }

        return sprintf($listTemplate, $atts["class"], $listHeadingTemplate, $items);
    }

    //Stolen from comments : http://php.net/manual/en/function.filesize.php thx Arseny Mogilev
    function human_filesize($bytes) {
        $bytes = floatval($bytes);
        $arBytes = array(
            array(
                "UNIT" => "Pb",
                "VALUE" => pow(1024, 5)
            ),
            array(
                "UNIT" => "Tb",
                "VALUE" => pow(1024, 4)
            ),
            array(
                "UNIT" => "Gb",
                "VALUE" => pow(1024, 3)
            ),
            array(
                "UNIT" => "Mb",
                "VALUE" => pow(1024, 2)
            ),
            array(
                "UNIT" => "Kb",
                "VALUE" => 1024
            ),
            array(
                "UNIT" => "Bytes",
                "VALUE" => 1
            ),
        );

        foreach($arBytes as $arItem)
        {
            if($bytes >= $arItem["VALUE"])
            {
                $result = $bytes / $arItem["VALUE"];
                $result = str_replace(",", "." , strval(round($result, 2)))." ".$arItem["UNIT"];
                break;
            }
        }
        return $result;
    }

    function rearrange_files_by_date($dir, $files)
    {
         $arr = array();
         $i = 0;
         foreach($files as $filename) {
           if ($filename != '.' && $filename != '..') {
             if (filemtime($dir.$filename) === false) return false;
             $dat = date("YmdHis", filemtime($dir.$filename));
             $arr[$dat . "," . $i++] = $filename;
           }
         }
         if (!ksort($arr)) return false;
         return $arr;
    }

    //Remove slashes from the start and end of the path if they exist
    function _check_for_slashes($folder)
    {
        $fixedPath = rtrim ($folder, '/');
        $fixedPath = ltrim ($fixedPath, '/');
        return $fixedPath;
    }

    function _flip_slahes($folder)
    {
        return str_replace("/", "\\", $folder);
    }

    function _regex_remove($str="", $regex, $replacememnt=" ")
    {
        $output = preg_replace($regex, $replacememnt, $str);

        //Add space before cap borrowed from: http://stackoverflow.com/a/1089681/4621469
        $output = preg_replace('/(?<!\ )[A-Z]/', ' $0', $output);

        // Trim whitespace
        return trim($output);        
    }

    function _pretty_filename($str="")
    {

        $regex = '/-|_| |\.[a-z0-9]*$/';
        $output = preg_replace($regex, ' ', $str);

        //Add space before cap borrowed from: http://stackoverflow.com/a/1089681/4621469
        $output = preg_replace('/(?<!\ )[A-Z]/', ' $0', $output);

        // Trim whitespace
        return trim($output);

    }

} // end class


add_action( 'init', 'MM_FileList_Init', 5 );
function MM_FileList_Init()
{
    global $MM_FileList;
    $MM_FileList = new MM_FileList();
}
?>