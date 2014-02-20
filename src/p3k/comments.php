<?php
namespace p3k\comments;

function truncate($text, $length) {
  ob_start();
  $short = ellipsize_to_word($text, $length, '...', 10);
  ob_end_clean();
  return $short;
}

function parse($mf, $maxTextLength=150) {
  # print_r($mf);

  $published = false;
  $text = false;
  $url = false;
  $author = array(
    'name' => false,
    'photo' => false,
    'url' => false
  );

  if(array_key_exists('type', $mf) && in_array('h-entry', $mf['type']) && array_key_exists('properties', $mf)) {
    $properties = $mf['properties'];

    if(array_key_exists('author', $properties)) {
      $authorProperty = $properties['author'][0];
      if(is_array($authorProperty)) {

        if(array_key_exists('name', $authorProperty['properties'])) {
          $author['name'] = $authorProperty['properties']['name'][0];
        }

        if(array_key_exists('url', $authorProperty['properties'])) {
          $author['url'] = $authorProperty['properties']['url'][0];
        }

        if(array_key_exists('photo', $authorProperty['properties'])) {
          $author['photo'] = $authorProperty['properties']['photo'][0];
        }

      } elseif(is_string($authorProperty)) {
        $author['url'] = $authorProperty;
      }
    }

    if(array_key_exists('published', $properties)) {
      $published = $properties['published'][0];
    }

    if(array_key_exists('url', $properties)) {
      $url = $properties['url'][0];
    }

    // From http://indiewebcamp.com/comments-presentation#How_to_display

    #print_r($properties);

    // If the entry has an e-content, and if the content is not too long, use that
    if(array_key_exists('content', $properties)) {
      $content = $properties['content'][0]['value'];
      if(strlen($content) <= $maxTextLength) {
        $text = $content;
      }
    }

    // If there is no e-content, or if it is too long
    if($text == false) {
      // if the h-entry has a p-summary, and the text is not too long, use that
      if(array_key_exists('summary', $properties)) {
        $summary = $properties['summary'][0];
        if(strlen($summary) <= $maxTextLength) {
          $text = $summary;
        } else {
          // if the p-summary is too long, then truncate the p-summary
          $text = truncate($summary, $maxTextLength);
        }
      } else {
        // if no p-summary, but there is an e-content, use a truncated e-content
        if(array_key_exists('content', $properties)) {
          $content = $properties['content'][0]['value'];
          $text = truncate($content, $maxTextLength);
        }
      }
    }

    // If there is no e-content and no p-summary
    if($text == false) {
      // If there is a p-name, and it's not too long, use that
      if(array_key_exists('name', $properties)) {
        $name = $properties['name'][0];
        if(strlen($name) <= $maxTextLength) {
          $text = $name;
        } else {
          // if the p-name is too long, truncate it
          $text = truncate($name, $maxTextLength);
        }
      }
    }

  }

  return array(
    'author' => $author,
    'published' => $published,
    'text' => $text,
    'url' => $url
  );
}

