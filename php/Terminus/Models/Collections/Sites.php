<?php

namespace Terminus\Models\Collections;

use Terminus\Session;
use Terminus\Models\Collections\TerminusCollection;

class Sites extends TerminusCollection {

  /**
   * Give the URL for collection data fetching
   *
   * @return [string] $url URL to use in fetch query
   */
  protected function getFetchUrl() {
    $url = sprintf('users/%s/sites', Session::getValue('user_uuid'));
    return $url;
  }

  /**
   * Adds a model to this collection
   *
   * @param [stdClass] $model_data Data to feed into attributes of new model
   * @param [array]    $options    Data to make properties of the new model
   * @return [void]
   */
  protected function add($model_data, $options = array()) {
    //Skip sites that are still in the build process
    if (!isset($model_data->information)) {
      return false;
    }
    $model   = $this->getMemberName();
    $options = array_merge(
      array(
        'id'         => $model_data->id,
        'collection' => $this,
      ),
      $options
    );
    $model_data->information->id = $model_data->id;

    $this->models[$model_data->id] = new $model(
      $model_data->information,
      $options
    );
  }

  /**
   * Retrieves the site of the given UUID or name
   *
   * @param [string] $id UUID or name of desired site
   * @return [Site] $site
   */
  public function get($id) {
    $models = $this->getMembers();
    $list = $this->getMemberList('name', 'id');
    $site   = null;
    if (isset($models[$id])) {
      $site = $models[$id];
    } elseif (isset($list[$id])) {
      $site = $models[$list[$id]];
    }
    if ($site == null) {
      throw new \Exception(sprintf('Cannot find site with the name "%s"', $id));
    }
    return $site;
  }

}
