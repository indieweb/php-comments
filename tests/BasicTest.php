<?php
class BasicTest extends PHPUnit_Framework_TestCase {

  public function setUp() {
  }

  private function _testEquals($expected, $input) {
    $result = IndieWeb\comments\parse($input, 90);
    $this->assertEquals($expected, $result);
  }

  private $_refURL = 'http://caseorganic.com/post/1';

  private function buildHEntry($input, $author=false, $isReply=true) {
    $entry = array(
      'type' => array('h-entry'),
      'properties' => array(
        'author' => array(
          ($author ?: array(
            'type' => array('h-card'),
            'properties' => array(
              'name' => array('Aaron Parecki'),
              'url' => array('http://aaronparecki.com/'),
              'photo' => array('http://aaronparecki.com/images/aaronpk.png')
            )
          ))
        ), 
        'published' => array('2014-02-16T18:48:17-0800'),
        'url' => array('http://aaronparecki.com/post/1'),
      )
    );
    if($isReply) {
      $entry['properties']['in-reply-to'] = array($this->_refURL);
    }
    if(array_key_exists('content', $input)) {
      $entry['properties']['content'] = array(array(
        'html' => $input['content'],
        'value' => strip_tags($input['content'])
      ));
      unset($input['content']);
    }
    // The rest of the properties are all simple properties. Loop through and add them all as properties.
    foreach($input as $key=>$val) {
      $entry['properties'][$key] = array($val);
    }
    return $entry;
  }

  public function testBasicExample() {
    $result = IndieWeb\comments\parse($this->buildHEntry(array(
      'name' => 'post name', 
      'summary' => 'post summary', 
      'content' => 'this is some content'
    )), 90, $this->_refURL);
    $this->assertEquals(array(
      'type' => 'reply',
      'author' => array(
        'name' => 'Aaron Parecki',
        'photo' => 'http://aaronparecki.com/images/aaronpk.png',
        'url' => 'http://aaronparecki.com/'
      ),
      'published' => '2014-02-16T18:48:17-0800',
      'text' => 'this is some content',
      'url' => 'http://aaronparecki.com/post/1'
    ), $result);
  }

  public function testContentTooLongSummaryIsOk() {
    $result = IndieWeb\comments\parse($this->buildHEntry(array(
      'name' => 'post name', 
      'summary' => 'post summary', 
      'content' => '<p>this is some content but it is longer than 90 characters so the summary will be used instead</p>'
    )), 90, $this->_refURL);
    $this->assertEquals('reply', $result['type']);
    $this->assertEquals('post summary', $result['text']);
  }

  public function testContentTooLongSummaryTooLong() {
    $result = IndieWeb\comments\parse($this->buildHEntry(array(
      'name' => 'post name', 
      'summary' => 'in this case the post summary is also too long, so a truncated version should be displayed instead', 
      'content' => '<p>this is some content but it is longer than 90 characters so the summary will be used instead</p>'
    )), 90, $this->_refURL);
    $this->assertEquals('reply', $result['type']);
    $this->assertEquals('in this case the post summary is also too long, so a truncated version should be ...', $result['text']);
  }

  public function testContentTooLongNoSummary() {
    $result = IndieWeb\comments\parse($this->buildHEntry(array(
      'name' => 'post name', 
      'content' => '<p>this is some content but it is longer than 90 characters so it will be truncated because there is no summary</p>'
    )), 90, $this->_refURL);
    $this->assertEquals('reply', $result['type']);
    $this->assertEquals('this is some content but it is longer than 90 characters so it will be truncated ...', $result['text']);
  }

  public function testNoContentNoSummaryNameOk() {
    $result = IndieWeb\comments\parse($this->buildHEntry(array(
      'name' => 'post name'
    )), 90, $this->_refURL);
    $this->assertEquals('reply', $result['type']);
    $this->assertEquals('post name', $result['text']);
  }

  public function testNoContentNoSummaryNameTooLong() {
    $result = IndieWeb\comments\parse($this->buildHEntry(array(
      'name' => 'this is a really long post name'
    )), 20, $this->_refURL);
    $this->assertEquals('reply', $result['type']);
    $this->assertEquals('this is a really ...', $result['text']);
  }

  public function testNameIsSubstringOfContent() {
    $result = IndieWeb\comments\parse($this->buildHEntry(array(
      'name' => 'The name of the note ...',
      'content' => 'The name of the note is a substring of the content'
    )), 200, $this->_refURL);
    $this->assertEquals('reply', $result['type']);
    $this->assertEquals('The name of the note is a substring of the content', $result['text']);
  }

  public function testNamedArticleWithShortContent() {
    $result = IndieWeb\comments\parse($this->buildHEntry(array(
      'name' => 'Post Name',
      'content' => 'The name of the post is different from the content'
    )), 200, $this->_refURL);
    $this->assertEquals('reply', $result['type']);
    $this->assertEquals('The name of the post is different from the content', $result['text']);
  }

  public function testNamedArticleWithLongContent() {
    $result = IndieWeb\comments\parse($this->buildHEntry(array(
      'name' => 'Post Name',
      'content' => 'The name of the post is different from the content, but in this case the content is too long and should be truncated.'
    )), 40, $this->_refURL);
    $this->assertEquals('reply', $result['type']);
    $this->assertEquals('The name of the post is different ...', $result['text']);
  }

  public function testReplyIsRSVP() {
    $result = IndieWeb\comments\parse($this->buildHEntry(array(
      'name' => 'RSVP Yes',
      'content' => 'Going to tonight\'s #IndieWeb Dinner @21stAmendment, 18:00. Hope to see you there!',
      'rsvp' => 'yes'
    )), 200, $this->_refURL);
    $this->assertEquals('rsvp', $result['type']);
    $this->assertEquals('Going to tonight\'s #IndieWeb Dinner @21stAmendment, 18:00. Hope to see you there!', $result['text']);
    $this->assertEquals('yes', $result['rsvp']);
  }

  public function testIsLike() {
    $result = IndieWeb\comments\parse($this->buildHEntry(array(
      'name' => 'Liked this',
      'content' => 'liked this post',
      'like' => $this->_refURL
    )), 200, $this->_refURL);
    $this->assertEquals('like', $result['type']);
    $this->assertEquals('liked this post', $result['text']);
  }

  public function testIsRepostOf() {
    $result = IndieWeb\comments\parse($this->buildHEntry(array(
      'name' => 'Reposted this',
      'content' => 'Reposted this',
      'repost-of' => $this->_refURL
    )), 200, $this->_refURL);
    $this->assertEquals('repost', $result['type']);
    $this->assertEquals('Reposted this', $result['text']);
  }

  public function testIsRepost() {
    $result = IndieWeb\comments\parse($this->buildHEntry(array(
      'name' => 'Reposted this',
      'content' => 'Reposted this',
      'repost' => $this->_refURL
    )), 200, $this->_refURL);
    $this->assertEquals('repost', $result['type']);
    $this->assertEquals('Reposted this', $result['text']);
  }

  public function testIsNotInReplyTo() {
    $result = IndieWeb\comments\parse($this->buildHEntry(array(
      'name' => 'Post Name',
      'content' => 'The name of the post is different from the content, but in this case the content is too long and should be truncated.'
    ), false, false), 40, $this->_refURL);
    $this->assertEquals('mention', $result['type']);
    $this->assertEquals('The name of the post is different ...', $result['text']);
  }

  public function testNoMicroformatsIsMention() {
    $result = IndieWeb\comments\parse(array(), 200, $this->_refURL);
    $this->assertEquals('mention', $result['type']);
    $this->assertEquals('', $result['text']);
  }

  public function testAuthorIsURL() {
    $result = IndieWeb\comments\parse($this->buildHEntry(array(
      'name' => 'post name', 
      'summary' => 'post summary', 
      'content' => '<p>this is some content</p>'
    ), 'http://aaronparecki.com/'), 200, $this->_refURL);
    $author = $result['author'];
    $this->assertEquals('reply', $result['type']);
    $this->assertEquals(false, $author['name']);
    $this->assertEquals(false, $author['photo']);
    $this->assertEquals('http://aaronparecki.com/', $author['url']);
  }

  public function testAuthorIsHCard() {
    $result = IndieWeb\comments\parse($this->buildHEntry(array(
      'name' => 'post name', 
      'summary' => 'post summary', 
      'content' => '<p>this is some content</p>'
    )), 200, $this->_refURL);
    $author = $result['author'];
    $this->assertEquals('reply', $result['type']);
    $this->assertEquals('Aaron Parecki', $author['name']);
    $this->assertEquals('http://aaronparecki.com/images/aaronpk.png', $author['photo']);
    $this->assertEquals('http://aaronparecki.com/', $author['url']);
  }

  public function testAuthorIsHCardWithNoPhoto() {
    $result = IndieWeb\comments\parse($this->buildHEntry(array(
      'name' => 'post name', 
      'summary' => 'post summary', 
      'content' => '<p>this is some content</p>'
      ), array(
        'type' => array('h-card'),
        'properties' => array(
          'name' => array('Aaron Parecki'),
          'url' => array('http://aaronparecki.com')
        )
      )
    ), 200 , $this->_refURL);
    $this->assertEquals('reply', $result['type']);
    $this->assertEquals('Aaron Parecki', $result['author']['name']);
    $this->assertEquals('', $result['author']['photo']);
    $this->assertEquals('http://aaronparecki.com', $result['author']['url']);
  }

}

