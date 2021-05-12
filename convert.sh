#!/bin/sh 

pdffile=$1;
fileofscript=$0;
scriptdir=$(dirname $fileofscript);
poscript=$scriptdir/potracetowrite.php;
#convert pdf to ppm
echo "Creating ppm files"
pdftoppm $pdffile page

#convert all ppm to bitmap
echo "Creating bitmap files"
/usr/bin/mkbitmap *.ppm

#take the potrace of all pbm files:
echo "Creating svg files using potrace"
potrace -s -W1240pt *.pbm

#convert all files to write svg files
echo "converting each page to write page"
for file in page*.svg
do
	php $poscript -i $file --noducumenthead --nodocumenttail
done

echo "creating write document head and tail"
php $poscript --onlydocumenthead -o documenthead
php $poscript --onlydocumenttail -o documenttail

echo "combining all pages into a single write document"
cat documenthead ${file%-*}*_write.svg documenttail > ${pdffile%.pdf}.svg

echo "compressing the svg file"
gzip ${pdffile%.pdf}.svg
mv ${pdffile%.pdf}.svg.gz ${pdffile%.pdf}.svgz

echo "cleaning the directory"
rm page-*
rm documenthead
rm documenttail
