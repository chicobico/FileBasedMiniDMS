<?php
    /* 
        FileBasedMiniDMS.php    by Stefan Weiss (2017)
    */
    $version = "0.12";
    
    require(dirname(__FILE__) . "/config.php");
    
    
    /* ------------------------------------------------------ */
    /* --- Don't touch unless you know what you're doing! --- */
    /* ------------------------------------------------------ */
    $testmode = false;
    $ocrtotxt = false;
    
    $options = getopt("vdtohxl::");
    foreach ($options as $opt => $val) {
        switch ($opt) {
            case "v": // verbose
                $loglevel = 6;
                break;
            case "d": // debug
                $loglevel = 7;
                break;
            case "l": //logfile
                $logfile = empty($val)?"stdout":$val;
                break;
            case "t": // test mode, no actions
                $testmode = true;
                break;
            case "o": // ocr all
                $OCRPrefix = "";
                break;
            case "x":
                $ocrtotxt = true;// store OCR'ed text in txt-files
                break;
            case "h": // help
                print("FileBasedMiniDMS v$version\n");
                print("syntax: php FileBasedMiniDMS.php <options>\n");
                print("  -v          verbose (loglevel 6)\n");
                print("  -d          debug (loglevel 7)\n");
                print("  -l<file>    log to given filename. if no filename is given, logs are sent to stdout.\n");
                print("  -t          test mode, no modifications to files\n");
                print("  -x          save ocr'ed text to txt-file\n");
                print("  -o          perform OCR on all files in \$inboxfolder.\n");
                print("              (can be useful after the rules were changed and can be combined with -t)\n");
                exit(0);
        }
    }
    
    if ($logfile == "syslog") openlog("FileBasedMiniDMS", LOG_PID, LOG_USER);
    $now = new DateTime();
    $now->setTimezone(new DateTimeZone($timezone));
    
    if ($doOCR) {
    	trace(LOG_INFO, "Scanning for new scans: $inboxfolder\n");
    	$newscans = listAllFiles($inboxfolder);
    	
    	foreach ($newscans as $scan) {
            $scanpath_parts = pathinfo($scan);
            if (0 != strcasecmp("pdf", $scanpath_parts['extension']))
                continue;

            trace(LOG_DEBUG, "File Extension: ".$scanpath_parts['extension']."\n");
            trace(LOG_DEBUG, "File Name: ".$scanpath_parts['filename']."\n");
            
            // OCR new pdf's
        	if (fnmatch($matchWithoutOCR, $scanpath_parts['filename'], FNM_CASEFOLD))
        	{
                trace(LOG_DEBUG, "scan: $scan\n");
                $ocrfilename = getOCRfilename($scan);
                trace(LOG_DEBUG, "ocrfilename: $ocrfilename\n");

                $user_id = 501;//exec('stat -u "%u" "'. $scan .'"');
                if (!is_numeric($user_id))
                {
                    trace(LOG_ERROR, "Could not get uid of file $scan\n");
                    continue;
                }
        		$cmd = "docker run --name ocr --rm -u $user_id --cpu-quota=80000 -v \"" . dirname($scan) . ":/home/docker\" " .
        		       "$dockercontainer $ocropt \"" . basename($scan) . "\" \"" . basename($ocrfilename) . "\" 2>&1";
        		trace(LOG_DEBUG, "Run Docker: $cmd\n");
        		
        		unset($dockeroutput);
        		if (!$testmode) exec($cmd, $dockeroutput, $dockerret);
        		if ($dockerret == 0) {
        			trace(LOG_INFO, "OCR'd \"$scan\" with status $dockerret\n");
        			trace(LOG_DEBUG, "Docker output:\n " . implode("\n ", $dockeroutput) . "\n");
        			if (!$testmode) recyclefile($inboxfolder, $scan);
        		} else {
        			trace(LOG_ERR, "Docker output:\n " . implode(" \n", $dockeroutput) . "\n");
        		}
        		
        		$scan = $ocrfilename;
                $scanpath_parts = pathinfo($scan);
        	}
        	
    		// Rename new PDF's based on rules
	    	if ($doRenameAfterOCR && preg_match("/.*\.$OCRExtension$/i", $scanpath_parts['filename']))
	    	{
	    		unset($out);
	    		unset($namedate);
	    		// get text from first page only
	    		$cmd = "pdftotext -l 1 \"$scan\" - 2>&1";
	    		trace(LOG_DEBUG, "run: $cmd\n");
	    		
                if ($ocrtotxt) exec("pdftotext -l 1 \"$scan\" 2>&1");
	    		exec($cmd, $out, $ret);
	    		if ($ret == 0) {
        			trace(LOG_DEBUG, "pdftotext output:\n " . implode("\n ", $out) . "\n");
                    
        			// == rename rules
        			$namedate = findPdfDate($out);
                    // name: default should be original filename without starting-date and without hashtags
                    trace(LOG_DEBUG, "Start searching for matching RENAME rules: \n");
        			$namename = findPdfSubject($out, stripDateAndTags($scanpath_parts['filename']));
                    
                    gethashtags($scanpath_parts['filename'], $tags); // get tags from source filename and keep them
                    
                    trace(LOG_DEBUG, "Start searching for matching TAG rules: \n");
                    findPdfTags($out, $tags);
                    foreach($tags as &$tag) {
                        $tag = strtolower($tag);
                    }
                    $tags = array_unique($tags);
        			$nametags = "";
                    if (count($tags) > 0) {
                        // get tags from source filename and keep them
                        $nametags = " " . implode(" ", $tags);
                    }

                    // == check if move to directory structure is activated
                    if ($doOrganizeInDirectoryStructure) {
                        trace(LOG_DEBUG, "Start searching for matching PERSON rules: \n");
                        $person = trim(findPdfPersons($out), '/');

                        trace(LOG_DEBUG, "Start searching for matching CATEGORY rules: \n");
                        $category = trim(findPdfCategories($out), '/');

                        if($person && $category){
                            $category_folder_path = $archivefolder . "/" . $person . "/" . $category;
                        }
                        else {
                            $category_folder_path = $inboxfolder . "/not_sorted";
                        }

                        if (!is_dir("$category_folder_path") &&
                            !$testmode &&
                            !mkdir("$category_folder_path", 0777, true))
                            trace(LOG_ERR, "ERROR: mkdir(\"$category_folder_path\", 0777, true)\n");


                        trace(LOG_DEBUG, "category_folder_path: $category_folder_path\n");
                    }
        			
                    // == do rename
                    trace(LOG_DEBUG, "dirname: ".$scanpath_parts['dirname']."\n");
                    $basedir = $category_folder_path ? $category_folder_path : $scanpath_parts['dirname'];
        			$newname = $basedir . "/$namedate " . $namename . "$nametags." . $scanpath_parts['extension'];
                    
                    trace(LOG_DEBUG, "newname: ".$newname."\n");
                    trace(LOG_DEBUG, "scan: ".$scan."\n");

                    if ($newname == $scan) {
                        trace(LOG_DEBUG, "rename not required: $scan\n");
                        continue;
                    }
                    
                    $newname = getNextFreeFilename($newname);
        			trace(LOG_INFO, "Renaming $scan\n");
                    trace(LOG_INFO, "      to $newname\n");
                    if (!$testmode && !rename($scan, $newname))
                    {
                        trace(LOG_ERR, "Could not rename '$scan' to '$newname'\n");
                    }
        		} else {
        			trace(LOG_ERR, "pdftotext output:\n " . implode(" \n", $out) . "\n");
        		}
	    	}
    	}
    }
    
    if ($doTagging) {
	    trace(LOG_INFO, "Scanning for Tagging: $archivefolder\n");
	    $unusedFiles = listAllFiles($tagsfolder); //all by default, remove files from array if they still exist later
        
        $archiveFiles = listAllFiles($archivefolder);
    	
        foreach ($archiveFiles as $file) {
            $filepath_parts = pathinfo($file);
            $file_name = $filepath_parts['basename'];
            $file_path = $filepath_parts['dirname'];

            if (gettags($file_name, $tags) > 0) {
                // Process Hashtags
                foreach ($tags as $tag) {
                    if (!is_dir("$tagsfolder/$tag") &&
                        !$testmode &&
                        !mkdir("$tagsfolder/$tag", 0777, true))
                        trace(LOG_ERR, "ERROR: mkdir(\"$tagsfolder/$tag\", 0777, true)\n");
                    
                    $namewithoutthistag = preg_replace("/\s*#$tag/", "", $file_name);
                    if (NULL != $namewithoutthistag) {
                        $unusedFiles = array_diff($unusedFiles, array("$tagsfolder/$tag/$namewithoutthistag"));
                        if (file_exists("$tagsfolder/$tag/$namewithoutthistag"))
                            continue;
                        
                        // symlink does not work in webdav :(
                        // copy or link (hardlink)
                        trace(LOG_INFO, "linking \"$file_name\" to \"tags/$tag/$namewithoutthistag\"\n");
                        if (!$testmode &&
                            !link("$file_path/$file_name", "$tagsfolder/$tag/$namewithoutthistag"))
                            trace(LOG_ERR, "ERROR linking \"$file_path/$file_name\" to \"tags/$tag/$namewithoutthistag\"\n");
                    }
                }
	        }
	    }
	    if (!$testmode) cleanUpTagFolder($unusedFiles, $tagsfolder);
    }

    function findPdfTags($textarr, &$tagsarr) {
        global $tagrules;
        
        foreach ($tagrules as $tag => $rule) {
            $ORarr = explode(',', $rule);
            foreach ($ORarr as $search) {
                $ANDarr = explode('&', $search);
                if (matchAll($ANDarr, $textarr)) {
                    array_push($tagsarr, $tag);
                }
            }            
        }
    }
    
    function findPdfSubject($textarr, $default = "") {
        global $renamerules;
        foreach ($renamerules as $rule => $name) {
            $ORarr = explode(',', $rule);
            foreach ($ORarr as $search) {
                $ANDarr = explode('&', $search);
                if (matchAll($ANDarr, $textarr)) {
                    return $name;
                }
            }            
        }
        return $default;
    }

    function findPdfCategories($textarr) {
        global $categorizerules;
        foreach ($categorizerules as $rule => $folder_path) {
            $ORarr = explode(',', $rule);
            foreach ($ORarr as $search) {
                $ANDarr = explode('&', $search);
                if (matchAll($ANDarr, $textarr)) {
                    return $folder_path;
                }
            }            
        }
    }

    function findPdfPersons($textarr) {
        global $personrules;
        foreach ($personrules as $rule => $folder_path) {
            $ORarr = explode(',', $rule);
            foreach ($ORarr as $search) {
                $ANDarr = explode('&', $search);
                if (matchAll($ANDarr, $textarr)) {
                    return $folder_path;
                }
            }            
        }
    }
    
    function matchAll($searcharr, $linearr) {
        foreach ($searcharr as $search) {
            if (!matchInLines($search, $linearr))
                return false;
        }
        return true;
    }
    
    function matchInLines($search, $linearr) {
        foreach ($linearr as $line) {
            if (preg_match("/.*$search.*/i", $line)) {
                trace(LOG_DEBUG, "!$search matches '$line'\n");
                return true;
            }
        }
        trace(LOG_DEBUG, "!!$search did not match\n");
        return false;
    }
    
    function findPdfDate($textarr) {
        global $now;
        // find dates
        $namedate = $now->format('Y-m-d'); // default to today
        foreach ($textarr as $line) {
            unset($matches);
            if (preg_match("/([0-3][0-9]).([0-1][0-9]).(20[0-9][0-9])/", $line, $matches)) { // dd.mm.20yy
                $namedate = join("-", array($matches[3], $matches[2], $matches[1]));
                break;
            }
            unset($matches);
            if (preg_match("/([0-1][0-9]).([0-3][0-9]).(20[0-9][0-9])/", $line, $matches)) { // mm.dd.20yy
                $namedate = join("-", array($matches[3], $matches[1], $matches[2]));
                break;
            }
            unset($matches);
            if (preg_match("/(20[0-9][0-9]).([0-3][0-9]).([0-1][0-9])/", $line, $matches)) { // 20yy.mm.dd
                $namedate = join("-", array($matches[1], $matches[2], $matches[3]));
                break;
            }
        }
        return $namedate;
    }
    
    function recyclefile($basepath, $file) {
    	global $recyclebin;
    	
    	if (strlen($file) > strlen($basepath) &&
    		0 == strncmp($basepath, $file, strlen($basepath)))
    	{
    		$file = substr($file, strlen($basepath)+1);
    		trace(LOG_DEBUG, "recyclefile: removed basepath '$basepath' from file '$file'\n");
    	}
        		
    	if (!empty($recyclebin)) {
    		if (!is_dir(dirname("$recyclebin/$file")))
    			mkdir(dirname("$recyclebin/$file"), 0777, true);
    		if (is_dir($recyclebin))
    			rename("$basepath/$file", getNextFreeFilename("$recyclebin/$file"));
    	} else {
    		unlink("$basepath/$file");
    	}
    }
    
    function getNextFreeFilename($filepath) {
    	$out = $filepath;
        $file_parts = pathinfo($filepath);
    	
        $a = $file_parts['dirname'] . "/" . $file_parts['filename'];
        $b = $file_parts['extension'];
    	$i = 0;
    	
        if (!empty($b))
    	{
	    	while (file_exists($out)) {
	    		$out = $a . " " . ++$i . "." . $b;
	    	}
    	} else {
    		while (file_exists($out)) {
	    		$out = "$filepath." . ++$i;
	    	}
    	}
    	return $out;
    }
    
    function getOCRfilename($pdf) {
    	global $OCRExtension;
        $extension = "";
        $pdf_parts = pathinfo($pdf);

        if (strpos($pdf_parts['filename'], ".".$OCRExtension) == false) {
            $extension = "." . trim($OCRExtension, ".");
        }

    	return getNextFreeFilename($pdf_parts['dirname'] . "/" . trim($pdf_parts['filename']) . $extension .'.'. $pdf_parts['extension']);
    }
    
    // @returns count of tags found or FALSE on error
    function gettags ($str, &$tags) {
        $ret = preg_match_all("/#([^\.#\s]+)/", $str, $matches);
        $tags = $matches[1];
        return $ret;
    }
    
    function gethashtags ($str, &$tags) {
        $ret = preg_match_all("/(#[^\.#\s]+)/", $str, $matches);
        $tags = $matches[1];
        return $ret;
    }
    
    function stripDateAndTags($str) {
        $str = preg_replace("/\d\d\d\d-[0-1]\d-[0-3]\d\s*/", "", $str);
        $str = preg_replace("/\s*#[^\.#\s]+/", "", $str);
        $str = preg_replace("/\.ocr$/i", "", $str);
        return $str;
    }
    
    // $level should be one of LOG_DEBUG, LOG_INFO, LOG_ERR
    function trace($level, $message) {
        global $logfile, $loglevel, $timezone;
        
        if ($loglevel < $level) return;
        
        if ($logfile == "syslog") {
            syslog($level, $message);
        } else {
            $now = new DateTime();
            $now->setTimezone(new DateTimeZone($timezone));
            $message = $now->format('Y-m-d H:i:s') . " " . $message;
            if ($logfile == "stdout")
                echo $message;
            else
                file_put_contents($logfile, $message, FILE_APPEND);
        }
    }
    
    function listAllFiles($path) {
        $result = array(); 
        if (file_exists($path)) {
            $all = scandir($path);
            foreach ($all as $one) {
                if ($one == "." || $one == "..") continue;
                if (is_dir($path . '/' . $one))
                    $result = array_merge($result, listAllFiles($path . '/' . $one));
                else
                    $result[] = $path . '/' . $one;
            }
        }
        return $result;
    }
    
    function cleanUpTagFolder($unusedFiles, $tagspath) {
        foreach ($unusedFiles as $file) {
            trace(LOG_INFO, "Deleting $file\n");
            unlink($file);
        }
        
        // now delete empty tag-folders
        $folders = scandir($tagspath);
        foreach ($folders as $folder) {
            if ($folder == "." || $folder == "..") continue;
            $f = $tagspath . '/' . $folder;
            if (is_dir($f)) {
                $items = array_diff(scandir($f), array('.','..'));
                if (count($items) == 0)
                    rmdir($f);    // rmdir won't delete not-empty folders. so simply call this on every folder. but it'll produce PHP warnings.
            }
        }
    }
?>