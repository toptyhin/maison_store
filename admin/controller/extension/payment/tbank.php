<?php

class ControllerExtensionPaymenttbank extends Controller
{
    private $error = array();
    public function index()
    {
        $this->load->language('extension/payment/tbank');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('payment_tbank', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
        }
        $data['error_email_company'] = isset($this->error['error_email_company']) ? $this->error['error_email_company'] : '';
        $data['error_password']      = isset($this->error['error_password']) ? $this->error['error_password'] : '';
        $data['error_terminal']      = isset($this->error['error_terminal']) ? $this->error['error_terminal'] : '';
        $data['error_url']           = isset($this->error['error_url']) ? $this->error['error_url'] : '';

        $data['taxations']           = array(
            'osn'                   => $this->language->get('text_taxation_osn'),
            'usn_income'            => $this->language->get('text_taxation_usn_income'),
            'usn_income_outcome'    => $this->language->get('text_taxation_usn_income_outcome'),
            'esn'                   => $this->language->get('text_taxation_esn'),
            'patent'                => $this->language->get('text_taxation_patent'),
        );
        $data['payment_method_list'] = array(
            'full_prepayment'       => $this->language->get('full_prepayment'),
            'prepayment'            => $this->language->get('prepayment'),
            'advance'               => $this->language->get('advance'),
            'full_payment'          => $this->language->get('full_payment'),
            'partial_payment'       => $this->language->get('partial_payment'),
            'credit'                => $this->language->get('credit'),
            'credit_payment'        => $this->language->get('credit_payment'),
        );
        $data['payment_object_list'] = array(
            'commodity'             => $this->language->get('commodity'),
            'excise'                => $this->language->get('excise'),
            'job'                   => $this->language->get('job'),
            'service'               => $this->language->get('service'),
            'gambling_bet'          => $this->language->get('gambling_bet'),
            'gambling_prize'        => $this->language->get('gambling_prize'),
            'lottery'               => $this->language->get('lottery'),
            'lottery_prize'         => $this->language->get('lottery_prize'),
            'intellectual_activity' => $this->language->get('intellectual_activity'),
            'payment'               => $this->language->get('payment'),
            'agent_commission'      => $this->language->get('agent_commission'),
            'composite'             => $this->language->get('composite'),
            'another'               => $this->language->get('another'),
        );
        $data["ffd_version"] =  array(
            "ffd_1_2_disabled" => $this->language->get('ffd_1_2_disabled'),
            "ffd_1_2_enabled" => $this->language->get('ffd_1_2_enabled')
        );

        if (isset($this->request->post['payment_tbank_ffd_12_v'])) {
            $data['payment_tbank_ffd_12_v'] = $this->request->post['payment_tbank_ffd_12_v'];
        } else {
            $data['payment_tbank_ffd_12_v'] = $this->config->get('payment_tbank_ffd_12_v');
        }

        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/payment/tbank', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['action'] = $this->url->link('extension/payment/tbank', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);

        if (isset($this->request->post['payment_tbank_password'])) {
            $data['payment_tbank_password'] = $this->request->post['payment_tbank_password'];
        } else {
            $data['payment_tbank_password'] = $this->config->get('payment_tbank_password');
        }

        if (isset($this->request->post['payment_tbank_terminal'])) {
            $data['payment_tbank_terminal'] = $this->request->post['payment_tbank_terminal'];
        } else {
            $data['payment_tbank_terminal'] = $this->config->get('payment_tbank_terminal');
        }

        if (isset($this->request->post['payment_tbank_email_company'])) {
            $data['payment_tbank_email_company'] = $this->request->post['payment_tbank_email_company'];
        } else {
            $data['payment_tbank_email_company'] = $this->config->get('payment_tbank_email_company');
        }
        if (isset($this->request->post['payment_tbank_method'])) {
            $data['payment_tbank_method'] = $this->request->post['payment_tbank_method'];
        } else {
            $data['payment_tbank_method'] = $this->config->get('payment_tbank_method');
        }
        if (isset($this->request->post['payment_tbank_object'])) {
            $data['payment_tbank_object'] = $this->request->post['payment_tbank_object'];
        } else {
            $data['payment_tbank_object'] = $this->config->get('payment_tbank_object');
        }

        if (isset($this->request->post['payment_tbank_enabled_taxation'])) {
            $data['payment_tbank_enabled_taxation'] = $this->request->post['payment_tbank_enabled_taxation'];
        } else {
            $data['payment_tbank_enabled_taxation'] = $this->config->get('payment_tbank_enabled_taxation');
        }

        if (isset($this->request->post['payment_tbank_taxation'])) {
            $data['payment_tbank_taxation'] = $this->request->post['payment_tbank_taxation'];
        } else {
            $data['payment_tbank_taxation'] = $this->config->get('payment_tbank_taxation');
        }

        if (isset($this->request->post['payment_tbank_status'])) {
            $data['payment_tbank_status'] = $this->request->post['payment_tbank_status'];
        } else {
            $data['payment_tbank_status'] = $this->config->get('payment_tbank_status');
        }

        if (isset($this->request->post['payment_tbank_language'])) {
            $data['payment_tbank_language'] = $this->request->post['payment_tbank_language'];
        } else {
            $data['payment_tbank_language'] = $this->config->get('payment_tbank_language');
        }

        if (isset($this->request->post['payment_tbank_status'])) {
            $data['payment_tbank_sort_order'] = $this->request->post['payment_tbank_sort_order'];
        } else {
            $data['payment_tbank_sort_order'] = $this->config->get('payment_tbank_sort_order');
        }

        if (isset($this->request->post['payment_tbank_authorized'])) {
            $data['payment_tbank_authorized'] = $this->request->post['payment_tbank_authorized'];
        } else {
            $data['payment_tbank_authorized'] = $this->config->get('payment_tbank_authorized');
        }

        if (isset($this->request->post['payment_tbank_confirmed'])) {
            $data['payment_tbank_confirmed'] = $this->request->post['payment_tbank_confirmed'];
        } else {
            $data['payment_tbank_confirmed'] = $this->config->get('payment_tbank_confirmed');
        }

        if (isset($this->request->post['payment_tbank_canceled'])) {
            $data['payment_tbank_canceled'] = $this->request->post['payment_tbank_canceled'];
        } else {
            $data['payment_tbank_canceled'] = $this->config->get('payment_tbank_canceled');
        }

        if (isset($this->request->post['payment_tbank_rejected'])) {
            $data['payment_tbank_rejected'] = $this->request->post['payment_tbank_rejected'];
        } else {
            $data['payment_tbank_rejected'] = $this->config->get('payment_tbank_rejected');
        }

        if (isset($this->request->post['payment_tbank_reversed'])) {
            $data['payment_tbank_reversed'] = $this->request->post['payment_tbank_reversed'];
        } else {
            $data['payment_tbank_reversed'] = $this->config->get('payment_tbank_reversed');
        }

        if (isset($this->request->post['payment_tbank_refunded'])) {
            $data['payment_tbank_refunded'] = $this->request->post['payment_tbank_refunded'];
        } else {
            $data['payment_tbank_refunded'] = $this->config->get('payment_tbank_refunded');
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/payment/tbank', $data));
    }

    public function install()
    {
        $this->load->model('extension/payment/tbank');
        $this->model_extension_payment_tbank->install();
    }

    public function uninstall()
    {
        $this->load->model('extension/payment/tbank');
        $this->model_extension_payment_tbank->uninstall();
    }

    protected function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/payment/tbank')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!$this->request->post['payment_tbank_password']) {
            $this->error['error_password'] = $this->language->get('error_required');
        }

        if (!$this->request->post['payment_tbank_terminal']) {
            $this->error['error_terminal'] = $this->language->get('error_required');
        }

        return !$this->error;
    }
}