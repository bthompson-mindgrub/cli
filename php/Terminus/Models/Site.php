<?php

namespace Terminus\Models;

use stdClass;
use Terminus\Request;
use Terminus\Deploy;
use \TerminusCommand;
use \Terminus\Models\Environment;
use \Terminus\Models\SiteUserMembership;
use Terminus\Models\Organization;
use Terminus\Models\User;
use Terminus\Models\Collections\Environments;
use Terminus\Models\Collections\SiteUserMemberships;
use Terminus\Models\Collections\OrganizationSiteMemberships;
use Terminus\Models\Collections\SiteOrganizationMemberships;
use Terminus\Models\Collections\Workflows;
use Terminus\Models\TerminusModel;

class Site extends TerminusModel {
  public $bindings;

  protected $environments;
  protected $org_memberships;
  protected $user_memberships;
  protected $workflows;

  private $features;
  private $tags;

  /**
   * Object constructor
   *
   * @param [stdClass] $attributes Attributes of this model
   * @param [array]    $options    Options to set as $this->key
   * @return [Site] $this
   */
  public function __construct($attributes, $options = array()) {
    if (!is_object($attributes)) die(print_r($attributes,true));
    $must_haves = array(
      'name',
      'id',
      'service_level',
      'framework',
      'created',
      'memberships'
    );
    foreach ($must_haves as $must_have) {
      if (!isset($attributes->$must_have)) {
        $attributes->$must_have = null;
      }
    }
    parent::__construct($attributes, $options);

    $this->environments     = new Environments(array('site' => $this));
    $this->org_memberships  = new SiteOrganizationMemberships(
      array('site' => $this)
    );
    $this->user_memberships = new SiteUserMemberships(array('site' => $this));
    $this->workflows        = new Workflows(array('owner' => $this));
  }

  /**
  * Create a new Site
  * @param $options (array)
  *   @param $options label(string)
  *   @param $options name(string)
  *   @option $options organization_id(string)
  *   @option upstream_id(string)
  *
  * @return Workflow
  *
  */
  static function create($options = array()) {
    $data = array(
      'label' => $options['label'],
      'site_name' => $options['name']
    );

    if(isset($options['organization_id'])) {
      $data['organization_id'] = $options['organization_id'];
    }

    if(isset($options['upstream_id'])) {
      $data['deploy_product'] = array(
        'product_id' => $options['upstream_id']
      );
    }

    $user = new User(new stdClass(), array());
    $workflow = $user->workflows->create('create_site', array(
      'params' => $data
    ));

    return $workflow;
  }

  public function fetch() {
    $response = TerminusCommand::simple_request(sprintf('sites/%s?site_state=true', $this->id));
    $this->attributes = $response['data'];
    # backwards compatibility
    $this->information = $this->attributes;

    return $this;
  }

  /**
   * returns array of attributes
   */
  public function attributes() {
    $path = "attributes";
    $method = "GET";
    $atts = \TerminusCommand::request('sites', $this->get('id'), $path, $method);
    return $atts['data'];
  }

  /**
   * Fetch Binding info
   */
  public function bindings($type=null) {
    if (empty($this->bindings)) {
      $path = "bindings";
      $response = \TerminusCommand::request('sites', $this->get('id'), $path, "GET");
      foreach ($response['data'] as $id => $binding) {
        $binding->id = $id;
        $this->bindings[$binding->type][] = $binding;
      }
    }
    if ($type) {
      if (isset($this->bindings[$type])) {
        return $this->bindings[$type];
      } else {
        return false;
      }
    }
    return $this->bindings;
  }

  /**
   * Load site info
   */
  public function info($key = null) {
    $info = array(
      'id' => $this->id,
      'name' => null,
      'label' => null,
      'created' => null,
      'framework' => null,
      'organization' => null,
      'service_level' => null,
      'upstream' => null,
      'php_version' => null,
      'holder_type' => null,
      'holder_id' => null,
      'owner' => null,
    );
    foreach($info as $info_key => $datum) {
      if ($datum == null) {
        $info[$info_key] = $this->get($info_key);
      }
    }

    if($key) {
      return isset($info[$key]) ? $info[$key] : null;
    } else {
      return $info;
    }
  }

  /**
   * Update service level
   */
  public function updateServiceLevel($level) {
    $path = "service-level";
    $method = 'PUT';
    $data = $level;
    $options = array( 'body' => json_encode($data) , 'headers'=>array('Content-type'=>'application/json') );
    $response = \TerminusCommand::request('sites', $this->get('id'), $path, $method, $options);
    return $response['data'];
  }

  /**
   * Get upstream updates
   */
   public function getUpstreamUpdates() {
     $response = \TerminusCommand::request('sites', $this->get('id'), 'code-upstream-updates', 'GET');
     return $response['data'];
   }

  /**
   * Apply upstream updates
   * @param $environment_id string required -- environment name
   * @param $updatedb boolean (optional) -- whether to run update.php
   * @param $xoption boolean (optional) -- auto resolve merge conflicts
   */
  public function applyUpstreamUpdates($environment_id, $updatedb = true, $xoption = false) {
    $params = array(
      'updatedb' => $updatedb,
      'xoption'  => $xoption
    );

    $workflow = $this->workflows->create('apply_upstream_updates', array(
      'environment' => $environment_id,
      'params' => $params
    ));
    return $workflow;
  }

  /**
   * Create a new branch
   */
  public function createBranch($branch) {
    $data = array('refspec' => sprintf('refs/heads/%s', $branch));
    $options = array( 'body' => json_encode($data) , 'headers'=>array('Content-type'=>'application/json') );
    $response = \TerminusCommand::request('sites', $this->get('id'), 'code-branch', 'POST', $options);
    return $response['data'];
  }

  /**
   * Retrieve New Relic Info
   */
  public function newRelic() {
    $path = 'new-relic';
    $response = \TerminusCommand::request('sites', $this->get('id'), 'new-relic', 'GET');
    return $response['data'];
  }

  /**
   * Import Archive
   */
  public function import($url, $element) {
    $data = array(
      'url' => $url,
      'code' => 0,
      'database' => 0,
      'files' => 0,
      'updatedb' => 0
    );

    if($element == 'all') {
      $data = array_merge($data, array('code' => 1, 'database' => 1, 'files' => 1, 'updatedb' => 1));
    } elseif($element == 'database') {
      $data = array_merge($data, array('database' => 1, 'updatedb' => 1));
    } else {
      $data[$element] = 1;
    }

    $workflow = $this->workflows->create('do_import', array(
      'environment' => 'dev',
      'params' => $data
    ));
    return $workflow;
  }

  /**
   * Adds payment instrument of given site
   *
   * @params [string] $uuid UUID of new payment instrument
   * @return [Workflow] $workflow Workflow object for the request
   */
  public function addInstrument($uuid) {
    $args = array(
      'site'   => $this->id,
      'params' => array(
        'instrument_id' => $uuid
      )
    );
    $workflow = $this->workflows->create('associate_site_instrument', $args);
    return $workflow;
  }

  /**
   * Removes payment instrument of given site
   *
   * @params [string] $uuid UUID of new payment instrument
   * @return [Workflow] $workflow Workflow object for the request
   */
  public function removeInstrument() {
    $args = array(
      'site'   => $this->id,
    );
    $workflow = $this->workflows->create('disassociate_site_instrument', $args);
    return $workflow;
  }

  /**
   * Delete a branch from site remove
   *
   * @param [string] $branch Name of branch to remove
   * @return [void]
   */
  public function deleteBranch($branch) {
    $workflow = $this->workflows->create('delete_environment_branch', array(
      'params' => array(
        'environment_id' => $branch,
      )
    ));
    return $workflow;
  }

  /**
   * Delete a multidev environment
   *
   * @param [string] $env            Name of environment to remove
   * @param [boolean] $delete_branch True to delete branch
   * @return [void]
   */
  public function deleteEnvironment($env, $delete_branch) {
    $workflow = $this->workflows->create('delete_cloud_development_environment', array(
      'params' => array(
        'environment_id' => $env,
        'delete_branch'  => $delete_branch,
      )
    ));
    return $workflow;
  }

  /**
   * Owner handler
   */
  public function setOwner($owner = null) {
    if ((boolean)$this->getFeature('change_management')) {
      $new_owner = $this->user_memberships->get($owner);
      if ($new_owner == null) {
        Terminus::error(
          'The new owner must first be a user. Try adding with `site team`'
        );
      }
      $workflow = $this->workflows->create(
        'promote_site_user_to_owner',
        array('user_id' => $new_owner->get('id'))
      );
      return $workflow;
    }
  }

  /**
  * Code branches
  */
  function tips() {
    $path = 'code-tips';
    $data = \TerminusCommand::request('sites',$this->id, $path, 'GET');
    return $data['data'];
  }

  /**
  * Verifies if the given framework is in use
  *
  * @param $framework_name [String]
  *
  * @return [boolean]
  **/
  private function hasFramework($framework_name) {
    return isset($this->information->framework) && ($framework_name == $this->information->framework);
  }

  /**
   * Lists user memberships for this site
   *
   * @return [SiteUserMemberships] Collection of user memberships for this site
   */
  public function getSiteUserMemberships() {
    $this->user_memberships = $this->user_memberships->fetch();
    return $this->user_memberships;
  }

  /**
   * Returns a specific site feature value
   *
   * @param [string] $feature Feature to check
   * @return [mixed] $this->features[$feature]
   */
  public function getFeature($feature) {
    if(!isset($this->features)) {
      $response = TerminusCommand::simple_request(
        sprintf('sites/%s/features', $this->id)
      );
      $this->features = (array)$response['data'];
    }
    if(isset($this->features[$feature])) {
      return $this->features[$feature];
    }
    return null;
  }

  /**
   * Adds a tag to the site
   *
   * @param [string] $tag Tag to apply
   * @return [array] $response
   */
  public function addTag($tag, $org) {
    $params   = array($tag => array('sites' => array($this->id)));
    $response = TerminusCommand::simple_request(
      sprintf('organizations/%s/tags', $org),
      array('method' => 'put', 'data' => $params)
    );
    return $response;
  }

  /**
   * Removes a tag to the site
   *
   * @param [string] $tag Tag to remove
   * @return [array] $response
   */
  public function removeTag($tag, $org) {
    $response = TerminusCommand::simple_request(
      sprintf('organizations/%s/tags/%s/sites?entity=%s', $org, $tag, $this->id),
      array('method' => 'delete')
    );
    return $response;
  }

  /**
   * Deletes site
   *
   * @param [string] $tag Tag to remove
   * @return [array] $response
   */
  public function delete() {
    $response = TerminusCommand::simple_request(
      'sites/' . $this->id,
      array('method' => 'delete')
    );
    return $response;
  }

  /**
   * Returns given attribute, if present
   *
   * @param [string] $attribute Name of attribute requested
   * @return [mixed] $this->attributes->$attributes;
   */
  public function get($attribute) {
    if(isset($this->attributes->$attribute)) {
      return $this->attributes->$attribute;
    }
    return null;
  }

  /**
   * Returns tags from the site/org join
   *
   * @param [string] $org  UUID of organization site belongs to
   * @return [array] $tags Tags in string format
   */
  public function getTags($org) {
    if (isset($this->tags)) {
      return $this->tags;
    }
    $org_site_member = new OrganizationSiteMemberships(
      array('organization' => new Organization(new stdClass(), array('id' => $org)))
    );
    $org_site_member->fetch();
    $org = $org_site_member->get($this->id);
    $tags = $org->get('tags');
    return $tags;
  }

  /**
   * Returns all organization members of this site
   *
   * @param [string] $uuid UUID of organization to check for
   * @return [boolean] True if organization is a member of this site
   */
  public function organizationIsMember($uuid) {
    $org_ids       = $this->org_memberships->fetch()->ids();
    $org_is_member = in_array($uuid, $org_ids);
    return $org_is_member;
  }

  /**
   * Returns all organization members of this site
   *
   * @return [array] Array of SiteOrganizationMemberships
   */
  public function getOrganizations() {
    $orgs = $this->org_memberships->fetch()->all();
    return $orgs;
  }

}
