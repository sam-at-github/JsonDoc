<?php

use JsonRef\JsonDocs;
use JsonRef\JsonRefPriorityQueue;
use JsonRef\Uri;
use JsonRef\JsonRef;
use JsonRef\JsonLoader;
use PHPUnit\Framework\TestCase;

/**
 * Basic tests Uri class.
 */
class JsonDocsTest extends TestCase
{
  private static $basicJson;
  private static $basicRefsJson;

  public static function setUpBeforeClass() {
    self::$basicJson = file_get_contents(getenv('DATADIR') . '/basic.json');
    self::$basicRefsJson = file_get_contents(getenv('DATADIR') . '/basic-refs.json');
  }

  /**
   * Test travesing some doc and collecting JSON Refs as JsonRef objects.
   */
  public function testFindAndReplaceRefs() {
    $doc = json_decode(self::$basicRefsJson);
    $uri = new Uri('file://' . getenv('DATADIR') . '/basic-refs.json');
    $refQueue = new JsonRefPriorityQueue();
    [$ids, $refUris] = JsonDocs::parseDoc($doc, $refQueue, $uri);
    $this->assertEquals($refQueue->count(), 5);
    $jsonRef1 = $refQueue->extract();
    $jsonRef2 = $refQueue->extract();
    $jsonRef3 = $refQueue->extract();
    $jsonRef4 = $refQueue->extract();
    $this->assertTrue($jsonRef1 instanceof JsonRef);
    $this->assertTrue($jsonRef2 instanceof JsonRef);
    $this->assertEquals($jsonRef1->getPointer(), '');
    $this->assertEquals($jsonRef2->getPointer(), 'foo');
    $this->assertEquals($jsonRef3->getPointer(), '');
    $this->assertEquals($jsonRef4->getPointer(), '/D');
    $jsonRef1 =& $jsonRef1->getRef();
    $jsonRef2 =& $jsonRef2->getRef();
    $jsonRef1 = "XXX";
    $jsonRef2 = "YYY";
    $this->assertEquals($doc->A, "XXX");
    $this->assertEquals($doc->F, "YYY");
  }

  /**
   * Basic test of JsonDocs instance.
   */
  public function test() {
    $cache = new JsonDocs(new JsonLoader());
    $cache->loadUri(new Uri('file://' . getenv('DATADIR') . '/basic.json'));
    $cache->loadUri(new Uri('file://' . getenv('DATADIR') . '/basic-refs.json'));
    $this->assertTrue($cache->exists(new Uri('file://' . getenv('DATADIR') . '/basic.json')));
  }

  /**
   * Test static getPointer(). Work on any doc.
   */
  public function testGetPointer() {
    $doc = json_decode(self::$basicJson);
    $ref =& JsonDocs::getPointer($doc,'/a');
    $ref = 67;
    $this->assertEquals($doc->a, 67);
    $ref =& JsonDocs::getPointer($doc,'/b');
    $ref =& JsonDocs::getPointer($doc,'/c');
  }

  /**
   * Test static getPointer() more.
   */
  public function testGetPointerEmptyRoot() {
    $doc = json_decode(self::$basicJson);
    $ref = JsonDocs::getPointer($doc,'/');
    $this->assertEquals($doc, $ref);
    $ref = JsonDocs::getPointer($doc,'');
    $this->assertEquals($doc, $ref);
    $ref = JsonDocs::getPointer($doc,'/////');
    $this->assertEquals($doc, $ref);
  }

  /**
   * Test static getPointer() more.
   */
  public function testGetEmptyPointer() {
    $doc = json_decode(self::$basicJson);
    $ref = JsonDocs::getPointer($doc,'/');
    $this->assertEquals($doc, $ref);
    $ref = JsonDocs::getPointer($doc,'');
    $this->assertEquals($doc, $ref);
    $ref = JsonDocs::getPointer($doc,'/////');
    $this->assertEquals($doc, $ref);
  }

  /**
   * Test static getPointer() more.
   * @expectedException \JsonRef\Exception\ResourceNotFoundException
   */
  public function testGetNonPointer() {
    $doc = json_decode(self::$basicJson);
    $ref = JsonDocs::getPointer($doc,'/dne');
  }

  /**
   * Test pointer(). Lookup up the doc internally then get the pointer.
   */
  public function testPointer() {
    $cache = new JsonDocs(new JsonLoader());
    $uri = new Uri('file://' . getenv('DATADIR') . '/basic-refs.json');
    $cache->loadUri($uri);
    $uri->fragment = "/C/Value";
    $ref =& $cache->pointer($uri);
    $this->assertEquals($ref, "C-Value");
    $ref = 87;
    $this->assertEquals($cache->pointer($uri), 87);
  }

  /**
   * Test refs with JSON Pointer encoded pointers.
   */
  public function testEncodedPointers() {
    $cache = new JsonDocs(new JsonLoader());
    $uri = new Uri('file://' . getenv('DATADIR') . '/json-pointer-encoding.json');
    $doc = $cache->loadUri($uri);
    $this->assertEquals($doc->properties->tilda, "tilda");
    $this->assertEquals($doc->properties->slash, "slash");
    $this->assertEquals($doc->properties->percent, "percent");
  }

  /**
   * Test static pointer() more.
   * @expectedException \JsonRef\Exception\ResourceNotFoundException
   */
  public function testNonPointer() {
    $cache = new JsonDocs(new JsonLoader());
    $uri = new Uri('file://' . getenv('DATADIR') . '/basic-refs.json');
    $cache->loadUri($uri);
    $uri->fragment = "/C/0";
    $ref =& $cache->pointer($uri);
  }

  /**
   * Test implicit loading of another resource via following a ref.
   * basic-external-ref.json contains one such ref.
   */
  public function testGetLoading() {
    $cache = new JsonDocs(new JsonLoader());
    $cache->loadUri(new Uri('file://' . getenv('DATADIR') . '/basic-external-ref.json'));
    $this->assertEquals($cache->count(), 2);
    $this->assertEquals($cache->pointer(new Uri('file://' . getenv('DATADIR') . '/user-schema.json#/definitions/_id/minimum')), 0);
  }

  /**
   * Test ref to ref chain OK
   */
  public function testRefChain() {
    $cache = new JsonDocs(new JsonLoader());
    $doc = $cache->loadUri(new Uri('file://' . getenv('DATADIR') . '/basic-chained-ref.json'));
    $this->assertEquals($doc->B[0], 'X-Value');
  }

  public function dataRefThroughRef() {
    return [
      ['/ref-through-ref-1.json'],
      ['/ref-through-ref-2.json'],
      ['/ref-through-ref-3.json']
    ];
  }

  /**
   * Test ref through ref OK
   * @dataProvider dataRefThroughRef
   */
  public function testRefThroughRef($filename) {
    $cache = new JsonDocs(new JsonLoader());
    $doc = $cache->loadUri(new Uri('file://' . getenv('DATADIR') . $filename));
    $this->assertEquals($doc->a->x, 'treasure');
  }

  /**
   * Test 'id' is not an '$id'.
   * @expectedException \JsonRef\Exception\ResourceNotFoundException
   */
  public function testUseOfId() {
    $cache = new JsonDocs(new JsonLoader());
    $cache->loadUri(new Uri('file://' . getenv('DATADIR') . '/no-keyword-id.json'));
    $x = $cache->pointer(new Uri('file://' . getenv('DATADIR') . '/no-keyword-id.json#foo'));
  }

  public function testUseOfDollarId() {
    $cache = new JsonDocs(new JsonLoader());
    $cache->loadUri(new Uri('file://' . getenv('DATADIR') . '/dollar-id.json'));
    $var = $cache->pointer(new Uri('file://' . getenv('DATADIR') . '/dollar-id.json#bah'));
    $this->assertTrue(is_object($var));
    $this->assertTrue($var->id === 'baz');
  }

  public function dataRefLoopFails() {
    return [
      # ['/inv-ref-loop-l0.json'], # TODO: This actually passes.
      ['/inv-ref-loop-l2.json'],
      ['/inv-ref-loop-l3.json'],
      ['/inv-ref-loop-l4.json']
    ];
  }

  /**
   * Test 'id' is not an '$id'.
   * @dataProvider dataRefLoopFails
   * @expectedException \JsonRef\Exception\JsonReferenceException
   */
  public function testRefLoopFails($filename) {
    $cache = new JsonDocs(new JsonLoader());
    $cache->loadUri(new Uri('file://' . getenv('DATADIR') . $filename));
  }

  /**
   * Test throws with strictIds enabled.
   * @expectedException \JsonRef\Exception\JsonReferenceException
   */
  public function testStrictIds() {
    $cache = new JsonDocs(new JsonLoader(), true);
    $cache->loadUri(new Uri('file://' . getenv('DATADIR') . '/non-strict-ids.json'));
  }

  /**
   * Test does not throw with strictIds off.
   */
  public function testNotStrictIds() {
    $cache = new JsonDocs(new JsonLoader(), false);
    $cache->loadUri(new Uri('file://' . getenv('DATADIR') . '/non-strict-ids.json'));
    $this->assertEquals($cache->count(), 1);
  }


  /**
   * Test $refProp and $idProp.
   */
  public function testRefAndIdProp() {
    $cache = new JsonDocs(new JsonLoader(), false);
    $doc = $cache->loadUri(new Uri('file://' . getenv('DATADIR') . '/ref-id-prop.json'));
    $this->assertEquals($doc->c, 1);
    $this->assertEquals($doc->d->b, 1);
  }

  /**
   * Test load from string.
   */
  public function testLoadFromString() {
    $cache = new JsonDocs(new JsonLoader());
    $this->assertEquals($cache->loadDocStr("{}", new Uri('file:///tmp/foo')), json_decode("{}"));
    $this->assertEquals($cache->loadDocStr("[]", new Uri('file:///tmp/foo1')), []);
    $this->assertEquals($cache->loadDocStr("0", new Uri('file:///tmp/foo2')), 0);
    $this->assertEquals($cache->loadDocStr("\"string\"", new Uri('file:///tmp/foo3')), "string");
    $this->assertEquals($cache->loadDocStr("true", new Uri('file:///tmp/foo4')), true);
  }

  /**
   * Test load object.
   */
  public function testLoadFromObject() {
    $cache = new JsonDocs(new JsonLoader());
    $o = json_decode("{}");
    $this->assertEquals($o, $cache->loadDocObj($o, new Uri('file:///tmp/foo')));
  }

  /**
   * Test load from not a string which is not allowed.
   * @expectedException \TypeError
   */
  public function testLoadFromNotAString() {
    $cache = new JsonDocs(new JsonLoader());
    $cache->loadDocStr(json_decode("{}"), new Uri('file:///tmp/foo'));
  }

  /**
   * Test load from not a object which is not allowed.
   * @expectedException \TypeError
   */
  public function testLoadFromNotAObject() {
    $cache = new JsonDocs(new JsonLoader());
    $cache->loadDocObj("{}", new Uri('file:///tmp/foo'));
  }


  /**
   * Test load from junk string.
   * @expectedException \JsonRef\Exception\JsonDecodeException
   */
  public function testLoadFromInvalidString() {
    $cache = new JsonDocs(new JsonLoader());
    $cache->loadDocStr("{x}", new Uri('file:///tmp/foo'));
  }

  /**
   * Test getSrc
   */
  public function testgetSrc() {
    $cache = new JsonDocs(new JsonLoader());
    $cache->loadDocStr("{}", new Uri('file:///tmp/foo'));
    $this->assertEquals($cache->getSrc(new Uri('file:///tmp/foo')), "{}");
    $this->assertEquals($cache->getSrc(new Uri('file:///tmp/foo#/some/subschema')), "{}", "Fragment part is ignored");
    $uri = new Uri('file://' . getenv('DATADIR') . '/basic-refs.json');
    $target = file_get_contents($uri);
    $cache->loadUri($uri);
    $this->assertEquals(json_decode($cache->getSrc($uri)), json_decode($target));
    $uri->fragment = "foo";
    $this->assertEquals(json_decode($cache->getSrc($uri)), json_decode($target), "Fragment part is ignored");
  }

  public function testClear() {
    $cache = new JsonDocs(new JsonLoader());
    $cache->loadDocStr("{}", new Uri('file:///tmp/foo'));
    $this->assertEquals($cache->count(), 1);
    $cache->clear();
    $this->assertEquals($cache->count(), 0);
  }
}
