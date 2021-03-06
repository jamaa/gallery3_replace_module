<?php defined("SYSPATH") or die("No direct script access.");/**
 * Gallery - a web based photo album viewer and editor
 * Copyright (C) 2000-2012 Bharat Mediratta
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or (at
 * your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street - Fifth Floor, Boston, MA  02110-1301, USA.
 */
 
 include_once('class.iptcdata.php'); 
 
class Replace_Controller extends Controller {
  public function index($item_id) {
    $item = ORM::factory("item", $item_id);
    access::required("view", $item);
    access::required("edit", $item);
    echo '<br/>';
    print $this->_get_form($item_id);
  }

  public function handler($item_id) {
    access::verify_csrf();
    $item = ORM::factory("item", $item_id);
    access::required("view", $item);
    access::required("edit", $item);
    
    $iptcData = new iptcdata($item->thumb_path());
    $iptcTags = iptcdata::iptcTags();
    
    foreach($iptcTags as $key => $value) {
        $data[$key] = $iptcData->get($iptcTags[$key]);
    }
    
    $form = $this->_get_form();
    if ($form->validate()) {
      // @todo process the admin form
      $file = $_POST["file"];
      $pathinfo_new = pathinfo($file);
      $path = $item->file_path();
      
      $pathinfo_old = pathinfo($path);
      $renamed_old = $pathinfo_old['dirname'] . '/' . $pathinfo_old['filename'] . '_old.' . $pathinfo_old['extension'];
      rename($path, $renamed_old);
      
      $success = rename($pathinfo_new['dirname'] . '/' . $pathinfo_new['basename'], $pathinfo_old['dirname'] . '/' . $pathinfo_old['filename'] . '.' . $pathinfo_new['extension']);
      if($success) {
          unlink($renamed_old);
          unlink($pathinfo_new['dirname']);
          if($item->is_movie()) {
              $movie_relpath = $item->relative_path();
              $movie_relpath = str_replace($pathinfo_old['extension'], $pathinfo_new['extension'], $movie_relpath);
              $moviepath = $item->file_path();
              $moviepath = str_replace($pathinfo_old['extension'], $pathinfo_new['extension'], $moviepath);
              $meta = movie::get_file_metadata($moviepath);
              $moviename = $pathinfo_old['filename'] . '.' . $pathinfo_new['extension'];
              
              db::build()
                ->update("items")
                ->set("mime_type", $meta[2])
                ->set("name", $moviename)
                ->set("relative_path_cache", $movie_relpath)
                ->where("id", "=", $item_id)
                ->execute();
              if($_POST['iptc_keep']) {
                  $iptcData->write($data, $iptcTags,$item);
              }
              print $path;
              message::success(t("Movie-File Replaced Successfully. Set New Thumbnail With VideoThumb-Module."));
              json::reply(array("result" => "success"));
          }elseif($item->is_photo()) {
              if($_POST['iptc_keep']) {
                  $iptcData->write($data, $iptcTags,$item);
              }
              $item->thumb_dirty = 1;
              $item->resize_dirty = 1;
              $item->save();
              graphics::generate($item);
              message::success(t("Image-File Replaced Successfully."));
              json::reply(array("result" => "success"));
          }
      }else{
          json::reply(array("result" => "error", "html" => (string)$form));
      }
    }else{
      json::reply(array("result" => "error", "html" => (string)$form));
    }
  var_dump($duda);
  }

  private function _get_form($item_id) {
    $filesize = ini_get('upload_max_filesize') . 'B';
    $form = new Forge("replace/handler/{$item_id}", "", "post", array("id" => "g-Replace-form"));
    $group = $form->group("replace")->label(t("Replace"));
    $group  ->checkbox('iptc_keep')
            ->label(t("Keep IPTC-data of item to be replaced."))
            ->checked('checked');
    $group->upload("file")->label(t("File"))->size("60")->rules("allow[jpg,png,gif,flv,mpg,mp4,mpeg]|size[{$filesize}]|required")
      ->error_messages("required", "You must select a file")
      ->error_messages("invalid_type", "The file must be a jpg,png,gif,flv,mpg,mp4 or mpeg");
    $group->submit("")->value(t("Upload"));

    return $form;
  }
}
