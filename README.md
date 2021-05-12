# pdftowrite
Some Utilities To Convert PDF's toDocuments editable by Write by StylusLabs

pdftowrite.php: a php script to convert pdf files (created by programs like MS Work, latex, etc) to a form editable by Write. The images embedded in the pdf remain as images. Text are converted into paths. It uses pdftocairo

potracetowrite.php: a php script that takes a bitmap image and converts it into a format editable by Write
convert.sh: a script that converts a pdf (like scanned handwritten notes) and converts it into a format editable by Write. It used pdftoppm, mkbitmap (available with potrace) and potrace. It creates a document that is black and white. Everything in the final document is a path.
