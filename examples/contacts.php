<?php

# Use:
#
#   # Get a list of names of contacts with distinct details:
#   php contacts.php -l
#   # Get contact details for a specific name:
#   php contacts.php -l -c"Joe" -v > /tmp/Joe.json
#   $EDITOR /tmp/Joe.json # To remove excess output to make valid JSON
#   cp /tmp/Joe.json /tmp/Joe-updated.json
#   $EDITOR /tmp/Joe-updated.json # To make changes
#   # See what would be changed:
#   php contacts.php -r -o/tmp/Joe.json -n/tmp/Joe-updated.json
#   # Really make the changes:
#   php contacts.php -r -o/tmp/Joe.json -n/tmp/Joe-updated.json -R
#

include('../lib/my_metaname.php');
include('../lib/reusable.php');


# $contacts is a list of contact objects.
$contacts = array();

# $id_of_contact is a list where indices are used as IDs to look up contacts.
# $contacts cannot be used for this since the indices of contacts will change
# when it is sorted.
$id_of_contact = NULL;

# $domains_by_contact_id is a map from contact ID to list of domains
$domains_by_contact_id = array();

$operation = NULL;
$specific_contact = NULL;
$old_contact_file = NULL;
$new_contact_file = NULL;
$really_update = FALSE;
$verbose = FALSE;


function contact_compare ($a, $b)
{
  global $metaname;

  return compare (array (
    strcmp ($a->name, $b->name),
    strcmp ($a->organisation_name, $b->organisation_name),
    strcmp ($a->email_address, $b->email_address),
    strcmp ($metaname->postal_address_to_s($a->postal_address), $metaname->postal_address_to_s($b->postal_address)),
    strcmp ($metaname->phone_number_to_s($a->phone_number), $metaname->phone_number_to_s($b->phone_number)),
    strcmp ($metaname->phone_number_to_s($a->fax_number), $metaname->phone_number_to_s($b->fax_number))
  ));
}


function store_contact ($contact, $domain)
{
  global $contacts, $domains_by_contact_id;

  # Ensure that $contact is present in the list of contacts (and therefore has
  # an ID, which is its index in that list)
  set_push ($contacts, $contact);

  $contact_id = array_search ($contact, $contacts);

  # Ensure that a list of domains exists under the $contact_id key
  if (! array_key_exists ($contact_id, $domains_by_contact_id))
  {
    $domains_by_contact_id [$contact_id] = array ();
  }

  set_push ($domains_by_contact_id [$contact_id], $domain);
}


function load_domain_names ()
{
  global $metaname, $contacts, $id_of_contact;

  $DOMAIN_NAMES_JSON_FILE = "domain_names.json";
  $IGNORE_CACHE = TRUE;

  if ($IGNORE_CACHE)
  {
    $domain_names = $metaname->domain_names();
    $f = fopen ($DOMAIN_NAMES_JSON_FILE, "w");
    fwrite ($f, json_encode ($domain_names, JSON_PRETTY_PRINT));
    fclose ($f);
  }
  else {
    $domain_names = json_decode (file_get_contents ($DOMAIN_NAMES_JSON_FILE));
  }

  foreach ($domain_names as $domain)
  {
    store_contact ($domain->contacts->registrant, $domain);
    store_contact ($domain->contacts->admin, $domain);
    store_contact ($domain->contacts->technical, $domain);
  }

  # (Shallow) clone the $contacts array before it is sorted so that it is still
  # possible to look up contacts by ID.
  $id_of_contact = $contacts;
  usort ($contacts, "contact_compare");
}


function domains_listing_contact ($contact)
{
  global $id_of_contact, $domains_by_contact_id;

  $contact_id = array_search ($contact, $id_of_contact);
  if ($contact_id === FALSE)
  {
    fail ("No such contact: ".json_encode ($contact, JSON_PRETTY_PRINT));
  }
  return $domains_by_contact_id [$contact_id];
}


function list_contacts ()
{
  global $contacts, $specific_contact, $verbose;

  foreach ($contacts as $contact)
  {
    if ($specific_contact == null || $specific_contact == $contact->name)
    {
      $domains = domains_listing_contact ($contact);
      #print $metaname->contact_to_s ($contact)."\n";
      print $contact->name."\n";
      if ($verbose)
      {
        print json_encode ($contact, JSON_PRETTY_PRINT) . "\n";
      }
      foreach ($domains as $domain)
      {
        print "\t$domain->name\t";
        if ($domain->contacts->registrant == $contact) print "R";
        if ($domain->contacts->admin == $contact) print "A";
        if ($domain->contacts->technical == $contact) print "T";
        print "\n";
      }
      print "\n";
    }
  }
}


function replace_contacts ($old_contact_file, $new_contact_file)
{
  global $metaname, $really_update;

  $old_contact = json_decode (read_file ($old_contact_file));
  $new_contact = json_decode (read_file ($new_contact_file));
  $domains = domains_listing_contact ($old_contact);
  foreach ($domains as $domain)
  {
    print "Updating ".$domain->name."\n";
    if (! $really_update)
    {
      write_to_file_as_json ($domain->contacts, "/tmp/before.json");
    }
    if ($domain->contacts->registrant == $old_contact) $domain->contacts->registrant = $new_contact;
    if ($domain->contacts->admin      == $old_contact) $domain->contacts->admin      = $new_contact;
    if ($domain->contacts->technical  == $old_contact) $domain->contacts->technical  = $new_contact;
    if (! $really_update)
    {
      write_to_file_as_json ($domain->contacts, "/tmp/after.json");
      print `diff /tmp/before.json /tmp/after.json`;
    }
    else {
      $metaname->update_contacts ($domain->name, $domain->contacts);
    }
  }
}


function option ($arg, $option)
{
  if (0 === strpos ($arg, $option))
  {
    return substr ($arg, strlen ($option));
  }
  return FALSE;
}


function parse_command_line ()
{
  global $argv, $operation, $specific_contact, $old_contact_file, $new_contact_file, $really_update, $verbose;

  foreach ($argv as $i => $arg)
  {
    # The first argument is the script name and should be ignored
    if ($i == 0) continue;
    switch ($arg)
    {
      case "-l": # List
        $operation = "list";
        break;
      case "-r": # Replace
        $operation = "replace";
        break;
      case "-R": # Really update
        $really_update = TRUE;
        break;
      case "-v": # Verbose
        $verbose = TRUE;
        break;
      default:
        if ($param = option ($arg, "-c")) # Specific contact name
        {
          $specific_contact = $param;
        }
        if ($param = option ($arg, "-o")) # Old contact details
        {
          $old_contact_file = $param;
        }
        if ($param = option ($arg, "-n")) # New contact details
        {
          $new_contact_file = $param;
        }
    }
  }
}


parse_command_line ();
load_domain_names ();
switch ($operation)
{
  case "list":
    list_contacts ();
    break;
  case "replace":
    if ($old_contact_file === NULL) fail ("Old contact file (-o option) must be specified for replace");
    if ($new_contact_file === NULL) fail ("New contact file (-n option) must be specified for replace");
    replace_contacts ($old_contact_file, $new_contact_file);
    break;
  default:
    fail("No operation specified");
}

?>
