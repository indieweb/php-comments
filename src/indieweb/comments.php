<?php
namespace IndieWeb\comments;

function truncate($text, $length) {
  ob_start();
  $short = ellipsize_to_word($text, $length, '...', 10);
  ob_end_clean();
  return $short;
}

function parse($mf, $maxTextLength=150, $refURL=false) {
  // When parsing a comment, the $refURL is the URL being commented on.
  // This is used to check for an explicit in-reply-to property set to this URL.

  $type = 'mention';
  $published = false;
  $name = false;
  $text = false;
  $url = false;
  $author = array(
    'name' => false,
    'photo' => false,
    'url' => false
  );
  $rsvp = null;

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

    // If the post has an explicit in-reply-to property, verify it matches $refURL and set the type to "reply"
    if($refURL && array_key_exists('in-reply-to', $properties) && in_array($refURL, $properties['in-reply-to'])) {
      $type = 'reply';
    }

    // Check if the reply is an RSVP
    if(array_key_exists('rsvp', $properties)) {
      $rsvp = $properties['rsvp'][0];
      $type = 'rsvp';
    }

    // Check if this post is a "repost"
    if($refURL && array_key_exists('repost-of', $properties) && in_array($refURL, $properties['repost-of'])) {
      $type = 'repost';
    }

    // Also check for "u-repost" since some people are sending that. Probably "u-repost-of" will win out.
    if($refURL && array_key_exists('repost', $properties) && in_array($refURL, $properties['repost'])) {
      $type = 'repost';
    }

    // Check if this post is a "like"
    if($refURL && array_key_exists('like', $properties) && in_array($refURL, $properties['like'])) {
      $type = 'like';
    }

    // From http://indiewebcamp.com/comments-presentation#How_to_display

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
        $pname = $properties['name'][0];
        if(strlen($pname) <= $maxTextLength) {
          $text = $pname;
        } else {
          // if the p-name is too long, truncate it
          $text = truncate($pname, $maxTextLength);
        }
      }
    }

    // Now see if the "name" property of the h-entry is unique or part of the content
    if(array_key_exists('name', $properties)) {
      $nameSanitized = strtolower(strip_tags($properties['name'][0]));
      $nameSanitized = preg_replace('/ ?\.+$/', '', $nameSanitized); // Remove trailing ellipses
      $contentSanitized = strtolower(strip_tags($text));

      // If this is a "mention" instead of a "reply", and if there is no "content" property,
      // then we actually want to use the "name" property as the name and leave "text" blank.
      if($type == 'mention' && !array_key_exists('content', $properties)) {
        $name = $properties['name'][0];
        $text = false;
      } else {
        if($nameSanitized != $contentSanitized) {
          // If the name is the beginning of the content, we don't care
          if(!(strpos($contentSanitized, $nameSanitized) === 0)) {
            // The name was determined to be different from the content, so return it
            $name = $properties['name'][0];
          }
        }
      }
    }

  }

  $result = array(
    'author' => $author,
    'published' => $published,
    'name' => $name,
    'text' => $text,
    'url' => $url,
    'type' => $type
  );

  if($rsvp !== null) {
    $result['rsvp'] = $rsvp;
  }

  return $result;
}

