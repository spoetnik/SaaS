<?php namespace ProcessWire;

/**
 *
 * Saas
 *
 * @author Martijn de Geus
 *
 * ProcessWire 3.x
 * Copyright (C) 2011 by Ryan Cramer
 * Licensed under GNU/GPL v2, see LICENSE.TXT
 *
 * http://www.processwire.com
 * http://www.ryancramer.com
 *
 */

class Saas extends WireData implements Module, ConfigurableModule {

	/**
	 * Module information
	 */
	public static function getModuleInfo() {
		return array(
			'title' => 'Saas',
			'summary' => 'Restict access to Pages by Saas-id.',
			'version' => '0.0.1',
			'author' => 'Martijn de Geus',
			'href' => 'https://github.com/spoetnik/SaaS',
			'icon' => 'key',
			'autoload' => true,
			'requires' => 'ProcessWire>=3.0.0',
		);
	}

	/**
	 * Construct
	 */
	public function __construct() {
		parent::__construct();
		$this->no_access_action = 0;
		$this->replacement_title = 'Access denied';
		$this->replacement_message = 'You don\'t have permissions to access this page.';
	}

	/**
	 * Ready
	 */
	public function ready() {
		// Check if the current user has a saas_id, add one if needed
		if(!$this->wire('user')->isSuperuser() && !$this->wire('user')->isGuest() && !$this->wire('user')->saas_id) {
			$this->AddUserToSaas($this->wire('user'));
		}
		// Check the saas_id on configured saas_templates
		if(!$this->wire('user')->isSuperuser() && in_array($this->wire('page')->template->name, $this->saas_templates) ) {
			$this->addHookAfter('Page::render', $this, 'checkAccess');
		}
		// Add the saas_id on page save
		if(!$this->wire('user')->isSuperuser() ) {
			$this->addHookBefore('Pages::saveReady', $this, 'restrictAccess');
		}
	}

	/**
	 * Check if rendered page may be accessed
	 *
	 * @param HookEvent $event
	 */
	protected function checkAccess(HookEvent $event) {

		/* @var Page $page */
		$page = $event->object;
		/* @var User $user */
		$user = $this->wire('user');

		// Return if action is throw 404 and this is the 404 page
		if($this->no_access_action == 1 && $page->id === $this->wire('config')->http404PageID) return;

		// Match pages saas_id with users saas_id
		$matches = false;
		if($page->saas_id == $user->saas_id){
			$matches = true;
		}
		// Return if page matches user's saas_id
		if($matches) return;

		wire('log')->save('saas', 'Access Denied' );
		// Throw a 404 if this action is selected in the config
		if($this->no_access_action == 1) throw new Wire404Exception();

		// Replace the page markup
		$event->return = $this->replacementMarkup($page, $this->replacement_title, $this->replacement_message);
	}

	/**
	 * Get replacement markup to render
	 *
	 * @param Page $page The page that will have its rendered markup replaced
	 * @param string $title The title defined in the module config
	 * @param string $message The message defined in the module config
	 * @return string
	 */
	public function ___replacementMarkup($page, $title, $message) {
		$out = <<<EOD
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>$title</title>
</head>
<body style="font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; padding:20px;">
$message
</body>
</html>
EOD;
		return $out;
	}

	/**
	 * Add the saas_id to the saved page
	 *
	 * @param HookEvent $event
	 */
	protected function restrictAccess(HookEvent $event) {
		/* @var Page $page */
		$page = $event->arguments(0);
		/* @var User $user */
		$user = $this->wire('user');

		// If the page has a saas_id field, give it the value of the users saas_id.
		if($page->hasField('saas_id')) {
			$page->saas_id = $user->saas_id;
		}

	}

	/**
	 * Config inputfields
	 *
	 * @param InputfieldWrapper $inputfields
	 */
	public function getModuleConfigInputfields($inputfields) {
		$modules = $this->wire('modules');
		$tmpTemplates = wire('templates');

		foreach($tmpTemplates as $template) { // exclude system templates
			if($template->flags & Template::flagSystem) continue;
			$templates[] = $template;
        }

		/* @var InputfieldAsmSelect $f */
		$f = $modules->get("InputfieldAsmSelect");
		$f_name = 'saas_templates';
		$f->name = $f_name;
		$f->label = $this->_('Templates restricted by SaaS');
		$f->description = $this->_('Choose the templates for wich content will be restricted by SaaS');
        foreach($templates as $template) $f->addOption($template->name);
		$f->value = $this->$f_name;
        $inputfields->add($f);

		/* @var InputfieldRadios $f */
		$f = $modules->InputfieldRadios;
		$f_name = 'no_access_action';
		$f->name = $f_name;
		$f->label = $this->_('Action to take if the visitor may not access the rendered page');
		$f->addOption(0, $this->_('Replace the rendered markup'));
		$f->addOption(1, $this->_('Throw a 404 exception'));
		$f->value = $this->$f_name;
		$inputfields->add($f);

		/* @var InputfieldFieldset $f */
		$fs = $this->wire('modules')->InputfieldFieldset;
		$fs->label = $this->_('Replacement markup');
		$fs->description .= $this->_('If you need more advanced markup than is possible with the options below then you can hook `AccessByQueryString::replacementMarkup()`.');
		$fs->showIf = 'no_access_action=0';

		/* @var InputfieldText $f */
		$f = $this->wire('modules')->InputfieldText;
		$f_name = 'replacement_title';
		$f->name = $f_name;
		$f->label = $this->_('Meta title');
		$f->value = $this->$f_name;
		$fs->add($f);

		/* @var InputfieldTextarea $f */
		$f = $this->wire('modules')->InputfieldTextarea;
		$f_name = 'replacement_message';
		$f->name = $f_name;
		$f->label = $this->_('Message');
		$f->value = $this->$f_name;
		$f->rows = 3;
		$fs->add($f);

		$inputfields->add($fs);

		$modules->addHookAfter('saveConfig', $this, 'afterConfigSave');

	}

	/**
	 * Process the submitted config data
	 *
	 * @param HookEvent $event
	 */
	protected function afterConfigSave(HookEvent $event) {
		$tmpTemplates = wire('templates');

		foreach($tmpTemplates as $template) { // exclude system templates
			if($template->flags & Template::flagSystem) continue;
			$templates[] = $template;
		}

		if($event->arguments(0) != $this) return;
		$data = $event->arguments(1);
		if(!empty($data['saas_templates'])) {
			// Add saas_id field to selected templates
			foreach($templates as $template) {
				if(in_array($template->name, $data['saas_templates'])) {
					if($template->hasField('saas_id')) {
						continue;
					} else {

						$template->fields->add('saas_id');
						$template->fields->save();
					}
				} else {			
					if($template->hasField('saas_id')) {
						// remove SaaS field						
                        $template->fields->remove('saas_id');
						$template->fields->save();
					} else {
						continue;
					}
				}
			}
		}
		$event->arguments(1, $data);
	}

	/**
	 * Enter saas_id to users without one
	 * 
	 * @param Userobject $user
	 */
	private function AddUserToSaas($user){
		// The the current highest saas_id
		$table = $this->wire('fields')->get('saas_id')->getTable();
		$query = $this->wire('database')->query("SELECT data FROM $table ORDER BY data DESC LIMIT 1");
		$ids = $query->fetchAll(\PDO::FETCH_COLUMN);
		$newid = 0;
		//$ids returns an array (of 1) itterate over it, and find the highest value
		foreach($ids as $id){
			if($id > $newid) $newid = $id+1;
		}
		//add one, and add to user's saas_id field
		$this->wire('user')->saas_id = $newid;
		$this->wire('user')->save();

	}

	/**
	 * Create saas_id field on install
	 *
	 */
    public function ___install() {

        // create saas_id field on user template
        if(!$this->wire('fields')->saas_id) {
            $f = new Field();
            $f->type = 'FieldtypeInteger';
			$f->inputfield = 'InputfieldInteger';
			$f_name = 'saas_id';
			$f->collapsed = Inputfield::collapsedHidden;
			$f->name = $f_name;
			$f->label = $this->_('SaasiD to restrict access');
            $f->description = $this->_('This is used by the SaaS module to limit this user to only see the branch starting with this parent when viewing the page list in the admin. It also restricts editing access to just this branch.');
			try {
				$f->save();
			} catch(\Exception $e) {
				$this->error("Error creating 'saas_id' field: " . $e->getMessage());
			}

            // Add the saas_id to all userTemplates
            foreach($this->wire('config')->userTemplateIDs as $userTemplateId) {
                $userTemplate = $this->wire('templates')->get($userTemplateId);
                $userTemplate->fields->add($f);
                $userTemplate->fields->save();
            }

        }
    }

	/**
	 * Remove saas_id field from all templates on uninstall
	 * Remove saas_id field on uninstall
	 * 
	 */
    public function ___uninstall() {
        $fields = wire('fields');
        $templates = wire('templates');
        $field = $this->wire('fields')->saas_id;

        if($field) {
            // remove the saas_id field from all usertemplates
            foreach($this->wire('config')->userTemplateIDs as $userTemplateId) {
                $userTemplate = $this->wire('templates')->get($userTemplateId);
                $userTemplate->fields->remove($field);
                $userTemplate->fields->save();
            }
            // remove the saas_id field from all templates
			foreach($templates as $template) {
				if(!$template->hasField($field)) continue;
				$template->fields->remove($field);
				$template->fields->save();
            }
            // remove saas_id field
			$fields->delete($fields->get($field));
        }
    }


}
