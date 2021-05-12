<?php
/*
 * This script will take the output of potrace and convert it to Write format. things to keep in mind:
 * **paths that are enclosed in <g> tags are trated as a single stroke. I would rather avoid it.
 * **I will just extract the path elements and insert it into the write document
 * **potrace creates a transformation. I should apply the transformation to the d attribute before adding it to the output
 * **document width is fixed at 1240px with additional 10px margin on the sides.
 * **the document of potrace should be scale to fit this side
 * **the write document starts with documenthead and ends with documenttail
 * **every write page starts with pagegead and end with pagetail.
 * **path elements should go between pagehead and pagetail.
*/

//read the options.



// pmbfile is created by mkbitmap
$pmbfile="out-1.pbm";
//this is the write file.
$outputfile="test.svg";


$options = getopt("i:o:",array("nodocumenthead","nodocumenttail","onlydocumenthead","onlydocumenttail"));
//potraceoutfile is created by potrace using pmbfile
if (isset($options['i'])) {
	$potraceoutfile = $options['i'];
} else {
	$potraceoutfile = "input.svg";
};
if (isset($options['o'])) {
	$outputfile  = $options['o'];
} else {
	$outputfile = explode(".",$potraceoutfile)[0];
	$outputfile .= "_write.svg";
}


$documenthead='<svg id="write-document" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
<rect id="write-doc-background" width="100%" height="100%" fill="#808080"/>
<defs id="write-defs">

<script type="text/writeconfig">
  <int name="docFormatVersion" value="2" />
  <int name="pageNum" value="1" />
  <float name="xOffset" value="-217.441864" />
  <float name="yOffset" value="-9.88372135" />
</script>
</defs>';

$documenttail='<defs>
<style type="text/css"><![CDATA[
  #write-document, #write-doc-background { width: 1260px;  height: 3550px; }
]]></style>
</defs>
</svg>';

$pagehead='<svg class="write-page" color-interpolation="linearRGB" x="0" y="0" width="1240px" height="1755px" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
  <g class="write-content write-v3" width="1240" height="1755" xruling="0" yruling="0" marginLeft="0" papercolor="#FFFFFF" rulecolor="#9F0000FF">
    <g class="ruleline write-std-ruling write-scale-down" fill="none" stroke="#0000FF" stroke-opacity="0.624" stroke-width="1" shape-rendering="crispEdges" vector-effect="non-scaling-stroke">
      <rect class="pagerect" fill="#FFFFFF" stroke="none" x="0" y="0" width="1240" height="1755" />
    </g>';

$pagetail='</g></svg>';

//I will use domdocument class rather then XML reader. I need to find 
//i) the size of the potraceoutfile
//ii)transformation given by potrace
//iii) transformation the I need to appy to fit the output document to a page of width 1240px. Note Write template uses px whereas potrace 
//uses pt in the output file. 1pt = 4/3px.
//
//


function gettransformation($dom) {
	//transformations in the potrace generated files are in the first and only g element
	$gelement = $dom->getElementsByTagName('g')[0];
	$transformation=$gelement->attributes->getNamedItem('transform')->value;
	//transformation has a translate and scale. I will find both translation and scale factors seperately.
	$translate=explode(" ",$transformation)[0];
	$translate=explode("(",$translate)[1];
	$dtx = explode(",",$translate)[0];
	$dty = explode(",",$translate)[1];
	$dty=str_replace((')'),(''),$dty);
	$scale=explode(" ",$transformation)[1];
	$scale=explode("(",$scale)[1];
	$scx = explode(",",$scale)[0];
	$scy = explode(",",$scale)[1];
	$scy = str_replace((')'),(''),$scy);
	return array('dtx' => $dtx,'dty' => $dty,'scx' => $scx,'scy' => $scy);
}

//this function will take a point as a string of two integers and a transformation array (dtx,dty,scx,scy)
//and returns the transformed point as a string. translation is applied first and scale is applied next

function transformpoint($transformationarray,$point) {
	$pointx = explode(" ",$point)[0];
	$pointy = explode(" ",$point)[1];
	$last = substr($pointy,-1,1);
	$pointy = str_replace(array('z'),array(''),$pointy);
	$newx = ($transformationarray['scx']*$pointx+$transformationarray['dtx']);
	$newy = ($transformationarray['scy']*$pointy+$transformationarray['dty']);
	$newpoint =$newx." ".$newy;
	if ($last == 'z') { $newpoint .= 'z'; };
	//if (is_numeric($pointx)) {} else {echo "\nPPPP".$point."PPP\n";};
	return $newpoint;
}

//this function will apply a transformation to an svg path. it will modify the "d" attribute
//in the path element, there is an M following by two numbers (coordinates of the starting point)
//(note in path element, upper case letters specify absolute coordinated whereis lowercase letter specify relative coordinates)
//paths of potrace end with z.
//after the M command, write uses l (line to) whereas potrace uses c (a cubic bezier curve) 
//after c there are three pairs of points. the last pair is the end point of the curve. for simplicity, I will
//first convert a corveto to lineto as is also done by write. If I end up having a curve with sharp turns, than I might
//consider improving.
//
//this function takes the whole path element, and will return transformed path element

function transformcurve($transformationarray,$path) {
	//let me first find the points corresponding to the path.
	$delement = $path->attributes->getNamedItem('d')->value;
	//in the output of potrace, M, c and z are connected to the numbers, there is no space after M, c and before z.
	//the d attribute of the output of potrace is M followed by 2 numbers followed by z followed by many numbers with z at the end.
	//these strategies did not work. I also have l :( new strategy: traverse all numbers one by one. there is no space after M,m,l,c
	$allcoordinates = explode(" ",$delement);
	$command="M";

	$newpath="";
	for ($iter=0; $iter < count($allcoordinates)-1; $iter++) {
		$firstcharacter=substr($allcoordinates[$iter],0,1);
		if (str_replace(array('M','c','m','l'),array('','','',''),$firstcharacter) == '') {
			$command = $firstcharacter;
			//$newpath .= str_replace(array('c'),array('l'),$command);
			$newpath .= $command;
		};
		switch ($command) {
		case "M":
			//command M only appears on as the first character. it is already aded
			$pointx = ltrim($allcoordinates[$iter],"Mcml");
			$pointy = rtrim($allcoordinates[$iter+1],"z");
			$newpoint = transformpoint($transformationarray,$pointx." ".$pointy);
			$newpath .= $newpoint;
			if (substr($allcoordinates[$iter+1],-1,1) == 'z') {$newpath .= 'z ';} else {$newpath .= ' ';};
			$iter++;
			break;
		case "m":
		case "l":
			$pointx = ltrim($allcoordinates[$iter],"Mcml");
			$pointy = rtrim($allcoordinates[$iter+1],"z");
			$scaletransform = array('dtx' => 0, 'dty'=>0, 'scx' =>	$transformationarray['scx'], 'scy' =>$transformationarray['scy']);;
			$newpoint = transformpoint($scaletransform,$pointx." ".$pointy);
			$newpath .= $newpoint;
			if (substr($allcoordinates[$iter+1],-1,1) == 'z') {$newpath .= 'z ';} else {$newpath .= ' ';};
			$iter++;
			break;
		case "c":
			$scaletransform = array('dtx' => 0, 'dty'=>0, 'scx' =>	$transformationarray['scx'], 'scy' =>$transformationarray['scy']);;
			$pointx = ltrim($allcoordinates[$iter],"Mcml");
			$pointy = rtrim($allcoordinates[$iter+1],"z");
			$newpoint = transformpoint($scaletransform,$pointx." ".$pointy);
			$newpath .= $newpoint." ";
			$pointx = ltrim($allcoordinates[$iter+2],"Mcml");
			$pointy = rtrim($allcoordinates[$iter+3],"z");
			$newpoint = transformpoint($scaletransform,$pointx." ".$pointy);
			$newpath .= $newpoint." ";
			$pointx = ltrim($allcoordinates[$iter+4],"Mcml");
			$pointy = rtrim($allcoordinates[$iter+5],"z");
			$newpoint = transformpoint($scaletransform,$pointx." ".$pointy);
			$newpath .= $newpoint;
			if (substr($allcoordinates[$iter+5],-1,1) == 'z') {$newpath .= 'z ';} else {$newpath .= ' ';};
			$iter += 5;
			break;
		default:
			//this is the case when no new command is given. the previous command continues.
		};
	};
	$newpath=trim($newpath);
	if (substr($newpath,-1,1) != 'z') {echo "\nPPP".$delement."\t".substr($newpath,-1,1)."PPP\n";};
	$newpath = '<path class="write-flat-pen" stroke-width="2.6" d="'.$newpath.'"/>'; 
	return $newpath;
};

$file=fopen($outputfile,'w');
if (!isset($options["onlydocumenthead"]) && !isset($options["onlydocumenttail"]))
{
	$dom = new DOMDocument();
	$svgstring=file_get_contents($potraceoutfile);
	$dom->loadXML($svgstring);

	//I also want the option to avoid putting $documenthead and/or $documenttail in order to process page by page and than concat all.
	if (!isset($options["nodocumenthead"])) {
		;
		} else {
			fwrite($file,$documenthead);
		};
	fwrite($file,$pagehead);
	$allpaths = $dom->getElementsByTagName('path');
	$transformation = gettransformation($dom);
	  foreach ($allpaths as $path) {
		$newpath = transformcurve($transformation,$path)."\n";
		fwrite($file,$newpath);
	  }
	fwrite($file,$pagetail);
	if ($options["nodocumenttail"] == "") {} else {
		fwrite($file,$documenttail);
	};
};
if (isset($options["onlydocumenthead"])) {fwrite($file,$documenthead);};
if (isset($options["onlydocumenttail"])) {fwrite($file,$documenttail);};
fclose($file);
?>
