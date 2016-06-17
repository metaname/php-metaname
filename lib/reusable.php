<?php

function set_push (&$set, $value)
{
  if ( ! in_array ($value, $set))
  {
    array_push ($set, $value);
  }
}


# $orders: list of comparisons of the parts of the object in the order that
# they're dominant in the ordering of the objects, for example:
#
#     $a = array ("name"=> "Joe", "age"=> 25);
#     $b = array ("name"=> "Jane", "age"=> 26);
#
#     compare (array (
#       strcmp ($a->name, $b->name),
#       $a->age - $b->age,
#     ));
#
# ..will order Jane first despite being older.
#
function compare ($orders)
{
  foreach ($orders as $order)
  {
    if ($order != 0) return $order;
  }
  return 0;
}


function fail ($message)
{
  fwrite (STDERR, "$message\n");
  exit (1);
}


function read_file ($path_to_file)
{
  $data = file_get_contents ($path_to_file);
  if ($data === FALSE) fail ("Could not read $path_to_file");
  return $data;
}


# Writes the JSON representation of $object to $path_to_file.
#
function write_to_file_as_json ($object, $path_to_file)
{
  $f = fopen ($path_to_file, "w");
  fwrite ($f, json_encode ($object, JSON_PRETTY_PRINT));
  fclose ($f);
}

?>
