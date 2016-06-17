<?php

include('JsonRpcClient.php');


class Metaname
{
  private  $api;
  private  $account_reference;
  private  $account_api_key;


  public function __construct($account_reference, $api_key)
  {
    global $METANAME_API_ENDPOINT;
    $this->api = new JsonRpcClient($METANAME_API_ENDPOINT);
    $this->account_reference = $account_reference;
    $this->account_api_key = $api_key;
  }


  function __call($name, $arguments)
  {
    $args = array_merge(array($this->account_reference, $this->account_api_key), $arguments);
    return call_user_func_array(array($this->api,$name), $args);
  }


  function postal_address_to_s($postal_address)
  {
    $parts = array($postal_address->line1,
                   $postal_address->line2,
                   $postal_address->city,
                   $postal_address->region,
                   $postal_address->postal_code,
                   $postal_address->country_code);
    return join(',', array_filter($parts));
  }


  function phone_number_to_s($phone_number)
  {
    if (! $phone_number)
      return 'null';
    $parts = array($phone_number->country_code,
                   $phone_number->area_code,
                   $phone_number->local_number);
    return '+'. join(' ', $parts);
  }


  function contact_to_s($contact)
  {
    $postal_address = $this->postal_address_to_s($contact->postal_address);
    $phone_number = $this->phone_number_to_s($contact->phone_number);
    $fax_number = $this->phone_number_to_s($contact->fax_number);
    return <<<EOF
Name:    $contact->name
Email:   $contact->email_address
Address: $postal_address
Phone:   $phone_number
Fax:     $fax_number
EOF;
    }

}

?>
