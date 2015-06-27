<?php

$reader = new MySqlReader();
$posts = $reader->getActivePostsWithGalleries();
//var_dump($posts[0]);
//$p = $reader->genJekyllPost($posts[0]);
//echo $p . "\n";
//$n = $reader->genJekyllPostFileName($posts[0]);
//echo $n . "\n";

$reader->generatePosts($posts);

$reader->close();

class MySqlReader {

    private $host       = "localhost";
    private $user       = "aszel";
    private $password   = "theokan27";
    private $dbname     = "aszel2011";
    private $connection;

    function __construct() {
        $this->connect();
    }

    public function connect() {
        $this->connection = new mysqli($this->host, $this->user, $this->password, $this->dbname);

        // Check connection
        if ($this->connection->connect_error) {
            die("Connection failed: " . $this->connection->connect_error);
        }
    }

    public function close() {
        @mysql_close($this->connection);
    }


    public function getActivePostsWithGalleries() {
        $result = $this->connection->query("SELECT * FROM `wp_posts` WHERE `post_status` = 'publish'");

        $posts = array();

        if($result->num_rows > 0) {
             while($row = $result->fetch_assoc()) {

                 // create post
                 $post = array();

                 $post_content = $row['post_content'];

                 // check if post_content contains gallery
                 $hasGal = $this->hasPostGalleryId($post_content);

                 if($hasGal) {

                     // filter gallery number
                     $galMarker = $this->getGalleryIdFromPostContent($post_content);
                     preg_match_all('!\d+!', $galMarker[0], $matches);
                     $galleryId = $matches[0][0];
                     $gallery = $this->getGalleryById($galleryId);

                     // get the images of the gallery
                     $postImages = $this->getImagesByGalleryId($galleryId);

                     // push everything into associative array
                     $post['post_id'] = $row['ID'];
                     $post['post_date'] = $row['post_date'];
                     $post['post_title'] = $row['post_title'];
                     $post['gallery'] = $gallery;
                     $post['images'] = $postImages;

                     // and add it to posts
                     array_push($posts, $post);
                 }
             }
        } else {
            echo "0 results for active posts";
            return null;
        }

        return $posts;
    }

    /**
     * Function checks if post contains a gallery
     * @param $post
     * @return bool
     */
    private function hasPostGalleryId($post) {

        $old = "[gallery=";
        $new = "[nggallery id=";

        if (strpos($post, $old) !== false) {
            return true;
        } elseif (strpos($post, $new) !== false) {
            return true;
        } else {
            return false;
        }
    }

    private function getGalleryIdFromPostContent($post_content) {
        $old = "/([gallery =)([0-9]+)]/";
        //$new = "/([nggallery id=)([0-9]+)]/";
        $new = "/([nggallery id=)([0-9]+)]/";
        preg_match ($new , $post_content, $result);
        return $result;
    }

    public function getGalleryById($id) {
        $q = "SELECT * FROM `wp_ngg_gallery` WHERE `gid` = $id";
        $result = $this->connection->query($q);
        $gallery = array();
        if($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                //var_dump($row);
                array_push($gallery, $row);
            }
        } else {
            echo "0 results";
            return null;
        }
        return $gallery;
    }

    public function getImagesByGalleryId($id) {
        $q = "SELECT * FROM `wp_ngg_pictures` WHERE `galleryid` = $id";
        $result = $this->connection->query($q);
        $filenames = array();
        if($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                //var_dump($row);
                array_push($filenames, $row['filename']);
            }
        } else {
            echo "0 results";
        }
        //print_r($filenames);
        return $filenames;
    }


    /**
     * Function generates a string which represents the content
     * of a Jekyll post.
     * @param $origPost
     * @return string
     */
    public function genJekyllPost($origPost) {
        $jekyllPost  = "---\n";
        $jekyllPost .= "layout:     gallerypost\n";
        $jekyllPost .= "title:      \"" . $origPost['post_title'] ."\"\n";
        $jekyllPost .= "date:       " . $origPost['post_date'] ."\n";
        $jekyllPost .= "categories: writing\n";
        $jekyllPost .= "permalink:  ash/:title\n";
        $jekyllPost .= "author:     Martin\n";
        $jekyllPost .= "meta:\n";
        $jekyllPost .= "tags:       []\n";
        $jekyllPost .= "images:\n";

        // path to gallery folder
        $newPath = $this->getGalleryPath($origPost);

        // loop gallery images
        foreach($origPost['images'] as $img) {
            $jekyllPost .= "    "  . $newPath  . "/" . $img . ":           600x400" . "\n";
        }

        $jekyllPost .= "---\n";
        $jekyllPost .= "\n";
        $jekyllPost .= "{% include gallery.html %}\n";

        return $jekyllPost;
    }

    public function getGalleryPath($origPost) {
        $oldPath = $origPost['gallery'][0]['path'];
        $search = "wp-content/gallery/";
        $newPath = str_replace($search, "", $oldPath);
        return $newPath;
    }

    public function genJekyllPostFileName($origPost) {
        $postDate = substr($origPost['post_date'], 0, 10);

        // append title (path) and fileending .md
        $newPath = $this->getGalleryPath($origPost);
        $filename = $postDate . "-" . $newPath . ".md";

        return $filename;
    }

    public function generatePosts($posts) {
        foreach($posts as $post) {
            $filecontent = $this->genJekyllPost($post);
            $filename = $this->genJekyllPostFileName($post);
            //echo $filecontent . "\n";
            $this->writeJekyllPostFile($filename, $filecontent);
        }
    }

    public function writeJekyllPostFile($filename, $filecontent) {
        file_put_contents ("posts/".$filename, $filecontent);
    }
}

/*
---
layout:     gallerypost
title:      "Okan back in the days"
date:       2002-07-07 01:01:00
categories: writing
permalink:  ash/:title
author:     Martin
meta:
tags:       []
images:
    2010_09_okan_revalerstr_2/delete.jpg:           600x400
---

{% include gallery.html %}
 */