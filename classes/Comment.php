<?php

class Comment {
	var $id;
	var $app_id;
	var $user_id;
	var $root_comment_id;
	var $parent_comment_id;
	var $subject;
	var $commment_date;
	var $comment;
	var $rating;
	var $org_id;
	var $constraint_level;
	function __construct( $id = null, $app_id = null, $user_id = null, $root_comment_id = null, $parent_comment_id = null, $subject = null, $comment_date = null, $comment = null, $rating = null, $org_id = null, $constraint_level = null ) {
		$this->id = $id;
		$this->app_id = $app_id;
		$this->user_id = $user_id;
		$this->root_comment_id = $root_comment_id;
		$this->parent_comment_id = $parent_comment_id;
		$this->subject = strip_tags($subject);
		$this->comment_date = $comment_date;
		$this->comment = strip_tags($comment);
		$this->rating = $rating;
		$this->org_id = $org_id;
		$this->constraint_level = $constraint_level;
	}
	
}
