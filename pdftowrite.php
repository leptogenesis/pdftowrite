#!/bin/sh 

<?php 
$INFILE="spectral_density.pdf";
$SVGFile="thesisdefense.svg";
$OUTFILE="compress.zlib://test.svgz";
$TEMPFILE="/tmp/temp";
$PDFTOSVG="/usr/bin/pdftocairo -svg ";
$SED="/usr/bin/sed";
/*
$PRE='<svg class="write-page" color-interpolation="linearRGB" x="10" y="0" width="1240px" height="1755px" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
  <g class="write-content write-v3" width="1240" height="1755" xruling="0" yruling="0" marginLeft="0" papercolor="#FFFFFF" rulecolor="#9F0000FF">
  <g class="ruleline write-std-ruling write-scale-down" fill="none" stroke="#0000FF" stroke-opacity="0.624" stroke-width="1" shape-rendering="crispEdges" vector-effect="non-scaling-stroke">
  <rect class="pagerect" fill="#FFFFFF" stroke="none" x="0" y="0" width="1240" height="1755" />
  </g>';
 */
$PRE='<svg class="write-page" color-interpolation="linearRGB" x="10" y="0" width="612pt" height="792pt" viewBox="0 0 612 792" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
  <g class="write-content write-v3" width="100%" height="100%" xruling="0" yruling="0" marginLeft="0" papercolor="#FFFFFF" rulecolor="#9F0000FF">
  <g class="ruleline write-std-ruling write-scale-down" fill="none" stroke="#0000FF" stroke-opacity="0.624" stroke-width="1" shape-rendering="crispEdges" vector-effect="non-scaling-stroke">
  <rect class="pagerect" fill="#FFFFFF" stroke="none" x="0" y="0" width="100%" height="100%" />
  </g>';
$POST='</svg>';
$HEAD='<svg id="write-document" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink\">
<rect id="write-doc-background" width="100%" height="100%" fill="#808080"/>';
$TAIL='</svg>';

//echo $HEAD > $TEMPFILE
//command="$PDFTOSVG $INFILE -| sed \"/<page>/,/<\/page>/p\" | sed  \"s/<page>/$PRE/g\" | sed  \"s/<\/page>/$POST/g\" >> $TEMPFILE"
//eval $command;
//echo $TAIL >> $TEMPFILE
//gzip --stdout $TEMPFILE > $OUTFILE
//


function readglyphs($documentname) 
{
	$glyphs = array();
	$xml = new XMLReader();
	$xml->open($documentname);
//move until the first symbol definition. glyphs are defined as paths inside symbol tags
	while ($xml->read()) 
	{ 
		if (($xml->name == 'symbol' || $xml->name == 'image') && $xml->nodeType != XMLReader::END_ELEMENT) {
			$element = new SimpleXMLElement($xml->readOuterXML());
			$innerxml = $xml->readInnerXML();
			if ($innerxml=="") {
				$innerxml = $xml->readOuterXML();
				};
			$xmlattributes=(array) $element->attributes();
			//$glyphs[$xmlattributes['id']]=$innerxml;
			$glyphs['#'.$xmlattributes['@attributes']['id']]=$innerxml;
		};
	};
	return $glyphs;
}
//this function returns a dom element.
function replaceglyphs($xmlelement, $glyphs) 
{
	if (strlen($xmlelement->asXML()) > 0) {
		$document = new DOMDocument();
		$doc = dom_import_simplexml($xmlelement); // DOMElement
		$document->loadXML($xmlelement->asXML());
		$document->formatOutput = true;
		//$document is the xml page that has use tags.
		$usenodes = $document->getElementsByTagName('use');
		$iter = $usenodes->length -1;
		while ($iter > -1) { //$node: DOMElement
			$node = $usenodes -> item($iter);
			if ($node->nodeName == 'use') {
				//$node is the "use" node. I need to find the x and y attributes and transform the path accordingly
				$glyphname=$node->attributes->getNamedItem('href')->value;
				if (isset($glyphs[$glyphname])) {
					$newnodestring = $glyphs[$glyphname]; //String
				} else {
					$newnodestring="<g></g>";
					echo "\n The requested use is neither a symbol nor a glyph. It is ".$glyphname."\n";
				}
				$newnode = new DOMDocument(); //DOMDocument
				//echo "creating dom for glyph\t".$glyphname."\n";
				$newnode->loadXML($newnodestring);
				if ($node->hasAttribute('x')) {
					$xshift=$node->attributes->getNamedItem('x')->value;
					$yshift=$node->attributes->getNamedItem('y')->value;
					$newnode->documentElement->setAttribute('transform','translate('.$xshift.','.$yshift.')');
				};
				if ($node->hasAttribute('transform')) {
					$newnode->documentElement->setAttribute('transform',$node->attributes->getNamedItem('transform')->value);
				};
				$newnodeindoc = $document -> importNode($newnode->documentElement,true);
				$node->parentNode->replaceChild($newnodeindoc,$node);
			};
			$iter--;
		}
		return $document->saveXML();
	};
}

//here I define all the glyphs appearing in svg. I should replace every <use...> is svg with the corresponding glyph
$glyphs = readglyphs($SVGFile);

$xml = new XMLReader();
$xml->open("$SVGFile");

while($xml->read() && $xml->name != 'page') 
{
	;
}
$count = 0;
file_put_contents($OUTFILE,$HEAD);
while($xml->name == 'page' )
{
	$element = new SimpleXMLElement($xml->readInnerXML());


	$page = replaceglyphs($element,$glyphs);
	$page = explode("\n",$page);
	array_Shift($page);
	array_Shift($page);
	$page = implode("\n",$page);

	$xml->next('page');
	$count++;
	

	file_put_contents($OUTFILE,$PRE,FILE_APPEND);
	file_put_contents($OUTFILE,$page,FILE_APPEND);
	file_put_contents($OUTFILE,$POST,FILE_APPEND);

	unset($element);
	unset($page);
};
file_put_contents($OUTFILE,$TAIL,FILE_APPEND);
echo $count;
?>




