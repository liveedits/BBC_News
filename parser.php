<?php

# MIT License
# 
# Copyright (c) 2018 LiveEdits.org
# 
# Permission is hereby granted, free of charge, to any person obtaining a copy
# of this software and associated documentation files (the "Software"), to deal
# in the Software without restriction, including without limitation the rights
# to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
# copies of the Software, and to permit persons to whom the Software is
# furnished to do so, subject to the following conditions:
# 
# The above copyright notice and this permission notice shall be included in all
# copies or substantial portions of the Software.
# 
# THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
# IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
# FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
# AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
# LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
# OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
# SOFTWARE.

# Go to base URL
$base_url = "https://www.bbc.com";
$path = "/news";

# Store directory
$store_dir = "www.bbc.com/";

# Class names to search for
$article_link_class="gs-c-promo-heading";
$article_body_class_list=["story-body", "vxp-media__body", "primary-content", "story"];
$article_remove_class_list=["share"];

# Options
$path_only = false;
$scramble_text = false;
$single_body = true;

# Character scramble map
$scramble = [
    ord('0')=>'1', ord('1')=>'2', ord('2')=>'3', ord('3')=>'4', ord('4')=>'5', ord('5')=>'6', ord('6')=>'7', ord('7')=>'8', ord('8')=>'9', ord('9')=>'0',
    
    ord('A')=>'B', ord('B')=>'C', ord('C')=>'D', ord('D')=>'E', ord('E')=>'F', ord('F')=>'G', ord('G')=>'H', ord('H')=>'I', ord('I')=>'J', ord('J')=>'K', 
    ord('K')=>'L', ord('L')=>'M', ord('M')=>'N', ord('N')=>'O', ord('O')=>'P', ord('P')=>'Q', ord('Q')=>'R', ord('R')=>'S', ord('S')=>'T', ord('T')=>'U', 
    ord('U')=>'V', ord('V')=>'W', ord('W')=>'X', ord('X')=>'Y', ord('Y')=>'Z', ord('Z')=>'A',
    
    ord('a')=>'b', ord('b')=>'c', ord('c')=>'d', ord('d')=>'e', ord('e')=>'f', ord('f')=>'g', ord('g')=>'h', ord('h')=>'i', ord('i')=>'j', ord('j')=>'k', 
    ord('k')=>'l', ord('l')=>'m', ord('m')=>'n', ord('n')=>'o', ord('o')=>'p', ord('p')=>'q', ord('q')=>'r', ord('r')=>'s', ord('s')=>'t', ord('t')=>'u', 
    ord('u')=>'v', ord('v')=>'w', ord('w')=>'x', ord('x')=>'y', ord('y')=>'z', ord('z')=>'a'
];

# Fill in the unchanged characters
for ($i = 0; $i < 256; $i++) {
    if (!array_key_exists($i, $scramble)) {
        $scramble[$i] = chr($i);
    }
}
ksort($scramble);

$ch = curl_init();
$timeout = 5;
curl_setopt($ch, CURLOPT_URL, $base_url.$path);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
$html = curl_exec($ch);
curl_close($ch);

$dom = new DOMDocument();
@$dom->loadHTML($html);

$finder = new DomXPath($dom);
$nodes = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $article_link_class ')]");

$links = [];

# Iterate over all the <a> tags
foreach($nodes as $linkNodes){
    # See if the href is in this node
    $div_href = $linkNodes->getAttribute('href');
    if ($div_href) {
        if (($path_only) and (substr($div_href, 0, strlen($path)) === $path)) {
            $links[] = $div_href;
        }
        else {
            $links[] = $div_href;
        }
    }
    else {
        print("Empty node?\n");
    }
    # Or in any of the child nodes
    foreach($linkNodes->getElementsByTagName('a') as $linkNode){
        $node_href = $linkNode->getAttribute('href');
        if ($node_href) {
            if (($path_only) and (substr($node_href, 0, strlen($path)) === $path)) {
                $links[] = $node_href;
            }
            else {
                $links[] = $node_href;
            }
        }
        else {
            print("Empty node?\n");
        }
    }
}

# Parse the link content
print_r($links);
$total = count($links);
$count = 0;

foreach($links as $link) {
    if (substr( $link, 0, 4 ) === "http") {
        $url = $link;
    }
    else {
        $url = $base_url.$link;
    }
    $ch = curl_init();
    $timeout = 5;
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    $html = curl_exec($ch);
    curl_close($ch);

    $dom = new DOMDocument();
    @$dom->loadHTML($html);

    # Strip out JS
    while (($r = $dom->getElementsByTagName("script")) && $r->length) {
        $r->item(0)->parentNode->removeChild($r->item(0));
    }

    # Strip CSS
    while (($r = $dom->getElementsByTagName("style")) && $r->length) {
        $r->item(0)->parentNode->removeChild($r->item(0));
    }
    
    # Strip specified classes of items
    $finder = new DomXPath($dom);
    foreach ($article_remove_class_list as $remove) {
        foreach($finder->query("//*[contains(@class,'$remove')]") as $remove_node) {
            $remove_node->parentNode->removeChild($remove_node);
        }
    }

    # Find any or all body classes items
    $finder = new DomXPath($dom);
    $nodes = [];
    foreach ($article_body_class_list as $body_class) {
        $query_nodes = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $body_class ')]");
        # print("Searching for " . $body_class . " results " . $query_nodes->length . "\n"); 
        if ($query_nodes->length) {
            $nodes[] = iterator_to_array($query_nodes);
        }
        
        # Do not continue if $single_body is set and the results are not empty
        if ($single_body and !empty($nodes)) {
            break;
        }
    }
    
    # Write file to folder
    $urlComponents = parse_url($url);
    $f = $store_dir.trim($urlComponents['path'],'/');

    $text = "";
    foreach($nodes as $node) {
        if (isset($node[0])) {
            $text .= $node[0]->textContent . "\n";
        }
    }

    print("Processing [$url]");
    if ($text) {
        print(" ...OK");
        $count++;
        
        # Make dirs if needed
        $dirname = dirname($f);
        if (!is_dir($dirname)) {
            mkdir($dirname, 0755, true);
        }
        print($f);

        # Write file to folder
        $myfile = fopen($f, "w") or die("Unable to open file!"); 
        
        if ($scramble_text) {
            print(", Scrambling text");
            $len = strlen($text);
            for ($i = 0; $i < $len; $i++) {
                $text[$i] = $scramble[ord($text[$i])];
            }
            print(" ...Done\n");
        }
        else {
            print("\n");
        }
        
        fwrite($myfile, $text);
        fclose($myfile);
    }
    else {
        print(" ...Skipped\n");
    }
}

print("Successfully parsed $count out of a total of $total\n");

?>
