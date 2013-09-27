<?php

$html = file_get_contents('Renters Rights.html');

function slugify($string) {
	if (strstr($string, '—'))
		$string = trim(explode('—', $string)[0]);
	
	$string = mb_strtolower($string);
	$string = str_replace(' ', '-', $string);
	$string = str_replace('_', '-', $string);
	
	return $string;
}

$out = $html;

// Replace <h3>Article X</h3>
$out = preg_replace_callback('/<h3>(Article \d+)<\/h3>/', function ($matches) {
	return '<h3 id="' . slugify($matches[1]) . '">' . $matches[1] . '</h3>';
}, $out);

// Replace <h2>Chapter Z — blah</h2>
$out = preg_replace_callback('/<h2>(Chapter \d+)/', function ($matches) {
	return '<h2 id="' . slugify($matches[1]) . '">' . $matches[1];
}, $out);


// Do stuff which requires a DOM
$doc = new DOMDocument(null, 'utf-8');
@$doc->loadHTML(mb_convert_encoding($out, 'HTML-ENTITIES', 'utf-8'));
$xpath = new DOMXPath($doc);

function isHeader(DOMNode $n) {
	return $n->nodeType == XML_ELEMENT_NODE and in_array(strtolower($n->tagName), ['h1', 'h2', 'h3', 'h4']);
}

// Having to use horrible replacements in here as $textNode->data escapes angle brackets
function autoLinkReferences(DOMNode $node) {
	// Don’t link inside .h-cite elements or headers
	if ($node->nodeType == XML_ELEMENT_NODE and strstr(' ' . $node->getAttribute('class') . ' ', ' h-cite '))
		return;
	
	if ($node->nodeType == XML_TEXT_NODE):
		$node->data = preg_replace_callback('/Article \d+/', function ($matches) {
			return 'REPLACE_BEGIN_ELEMENTa href="#' . slugify($matches[0]) . '"REPLACE_END_ELEMENT' . $matches[0] . 'REPLACE_BEGIN_ELEMENT/aREPLACE_END_ELEMENT';
		}, $node->data);
	endif;
	
	// Loop through each child node
	if ($node->hasChildNodes() and !isheader($node)):
		foreach ($node->childNodes as $n):
			autoLinkReferences($n);
		endforeach;
	endif;
}

// Loop over each child of the main article
$currentChapter = 0;
$currentArticle = 0;
$currentParagraph = 0;
foreach ($xpath->query('//article/*') as $el):
	if ($el->tagName == 'h2'):
		$currentChapter += 1;
	elseif ($el->tagName == 'h3'):
		$currentArticle += 1;
		$currentParagraph = 0;
	elseif ($el->tagName == 'p'):
		$currentParagraph += 1;
		
		$el->setAttribute('id', "article-{$currentArticle}-paragraph-{$currentParagraph}");
		
		autoLinkReferences($el);
	endif;
endforeach;

// Get the result
$out = $doc->C14N();
$out = str_replace('REPLACE_BEGIN_ELEMENT', '<', $out);
$out = str_replace('REPLACE_END_ELEMENT', '>', $out);

file_put_contents('Renters Rights (built).html', $out);
