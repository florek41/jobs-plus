<?php

/**
 * @author:Hoang Ngo
 */
class Expert_Saved_Controller extends IG_Request
{
    public function __construct()
    {
        add_action('je_credit_rules', array(&$this, 'settings'));
        add_action('wp_ajax_expert_saved_setting', array(&$this, 'save_settings'));
        add_action('je_expert_saving_process', array($this, 'check_user_can_post'));
        add_action('je_begin_expert_form', array(&$this, 'display_alert'));
    }

    function display_alert()
    {
        $settings = new Expert_Saved_Model();
        if (!User_Credit_Model::check_balance($settings->credit_use, get_current_user_id())) {
            ?>
            <div class="alert alert-warning">
                <?php echo sprintf(__('Your balance\'s not enough for posting new profile, please visit <a href="%s">here</a> for purchasing', je()->domain), get_permalink(ig_wallet()->settings()->plans_page)) ?>
            </div>
        <?php
        }
    }

    function check_user_can_post(JE_Expert_Model $model)
    {
        $settings = new Expert_Saved_Model();
        if ($settings->status == 0) {
            return;
        }

        if (!$model->status == 'je-draft') {
            return;
        }

        $this->is_user_can_post();

        if (!$this->is_user_can_post()) {
            User_Credit_Model::go_to_plans_page();
        } else {
            //remove points
            User_Credit_Model::update_balance(0 - $settings->credit_use, get_current_user_id());
            update_post_meta($model->id, 'je_expert_paid', 1);
        }
    }

    function is_user_can_post()
    {
        $user = new WP_User(get_current_user_id());

        $settings = new Expert_Saved_Model();
        //first check does this user in the role
        $roles = $settings->free_for;
        foreach ($user->roles as $role) {
            if (in_array($role, $roles)) {
                return true;
            }
        }
        //next check if this user already having free profiles
        if ($settings->free_from > 0) {
            if ($this->count_paid() > $settings->free_from) {
                return true;
            }
        }
        //finally check like normal
        return User_Credit_Model::check_balance($settings->credit_use, get_current_user_id());
    }

    function count_paid()
    {
        $models = JE_Expert_Model::model()->all_with_conditions(array(
            'meta_key' => 'je_expert_paid',
            'nopaging' => true
        ));
        return count($models);
    }

    function save_settings()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $model = new Expert_Saved_Model();
        $model->import(je()->post('Expert_Saved_Model'));

        if ($model->validate()) {
            $model->save();
            wp_send_json(array(
                'status' => 'success'
            ));
        } else {
            wp_send_json(array(
                'status' => 'fail',
                'errors' => $model->get_errors()
            ));
        }
        die;
    }

    function settings()
    {
        $model = new Expert_Saved_Model();
        $this->render('expert-saved/settings', array(
            'model' => $model
        ));
    }
}