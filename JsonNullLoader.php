<?php
namespace JsonRef;

use JsonRef\Exception\ResourceNotFoundException;
use JsonRef\JsonLoader;

/**
 * Loads what should be a raw JSON doc given a URI.
 */
class JsonNullLoader extends JsonLoader
{
  /**
   * Prevent JSON documents trying to follow external references. Most of the time you don't want to allow this.
   * @throws ResourceNotFoundException.
   */
  public function load($uri) {
    throw new ResourceNotFoundException("Loading external resources not permitted.");
  }
}
