<?
    /* ------------------------------------------------------ */
    /* ----------------- Configuration Area ----------------- */
    /* ------------------------------------------------------ */
    
    $doOCR = true;
    $doRenameAfterOCR = true;
    $doOrganizeInDirectoryStructure = true;
    $doTagging = true;
    
    // files which match this rule are considered to be OCR'd
    $matchWithoutOCR = "*"; // without file extension .pdf
    
    // the extension which shows that the file is already indexed e.g. scan.ocr.pdf
    $OCRExtension = "ocr"; // without file extension .pdf
    
    // ocrmypdf options
    // -s 	don't OCR pages with text again
	// -r	automatically rotate pages based on detected text orientation
	// -l	language (deu,enu,...)
    //  example: docker run --rm -u 1026 -v "/volume1/docker/ocr:/home/docker" jbarlow83/ocrmypdf -l deu Scan.pdf Out.pdf
    $ocropt = "-sr -l deu";
    $dockercontainer = "jbarlow83/ocrmypdf";
    
    // here are freshly scanned documents. to be OCR'd and renamed.
    $inboxfolder = "/Users/fa/Projects/PHP/FileBasedMiniDMS/TEST/INBOX";
    
    // Set $archivefolder to the folder which contains your documents for tagging.
    // Without trailing (back)slash!
    $archivefolder = "/Users/fa/Projects/PHP/FileBasedMiniDMS/TEST/Archive";
    
    // recycle-bin
    $recyclebin = "/Users/fa/Projects/PHP/FileBasedMiniDMS/TEST/#recycle";
    
    // In $tagsfolder your tags will be created. Please use a fresh folder.
    // Everything here is subject to be deleted! Without trailing (back)slash!
    $tagsfolder = "/Users/fa/Projects/PHP/FileBasedMiniDMS/TEST/TAGS";
    
    // $logfile is the path to a logfile OR "syslog" OR "stdout"
    //$logfile = dirname(__FILE__) . "/FileBasedMiniDMS.log";
    $logfile = "/Users/fa/Projects/PHP/FileBasedMiniDMS/FileBasedMiniDMS.log";
    
    // $loglevel can be 0 (none), 3 (error), 6 (info), 7 (all)
    $loglevel = 7;
    
    // $timezone. just for logging purposes.
    $timezone = 'Europe/Berlin';
    
    // first match is used
    $renamerules = array(
        // "rule" => "name"
    	"Meldebescheinigung&Sozialversicherung" => "Meldebescheinigung Sozialversicherung",
        "Grundsteuerbescheid" => "Grundsteuerbescheid",
        "Finanzamt" => "Finanzamt",
        "LBS&Bauspar" => "LBS Bausparen",
        "Deka&Jahresdepotauszug" => "Deka Jahresdepotauszug",
        "Deka" => "Deka",
        "Sparkasse&Dividende&Depot" => "Sparkasse Aktiendepot Dividende",
        "Apotheke" => "Apotheke",
        "Erdgas&Jahresrechnung" => "Erdgas Jahresrechnung",
        "Zahnarzt" => "Zahnarzt",
        "PVS BW" => "Arztrechnung PVS",
        "Arzt,Ärztin" => "Arzt",
        "Heilpraktiker" => "Heilpraktiker",
        "Vodafone GmbH" => "Vodafone",
        );
    
    // first match is used
    $categorizerules = array(
        // "rule" => "folder_path" (without leading and trailing slashes)
        "Fidor&Bank,Bank,Konto" => "bank",
        "Rechnung"              => "rechnungen",
        "Finanzamt"             => "finanzamt",
        "Persönliches"          => "persoenliches",
        "Arbeit,Lohn"           => "arbeit",
        "Zeugniss,Zeugnis"      => "zeugnisse",
        "Auto,KFZ"              => "auto",
        "Wohnung"               => "wohnung",
        "Versicherung"          => "versicherung",
    );

    // first match is used
    $personrules = array(
        // "rule"       => "folder_path" (without leading and trailing slashes)
        "tippmate"      => "tippmate",
    	"florian"	    => "florian",
    	"magdalena"		=> "magdalena",
        "felix"         => "felix",
        "jakob"         => "jakob",
    	);
    
    // all are applied and concaternated
    $tagrules = array(
        // "tag"                => "rule"
    	"#rechnung"		        => "Rechnung",
        "#beitragsanpassung"    => "Beitragsanpassung",
    	);
?>