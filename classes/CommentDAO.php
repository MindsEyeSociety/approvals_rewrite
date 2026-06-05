<?php
include_once("classes/Comment.php");

class CommentDAO {
	var $db;

	function __construct( $db ) {
		$this->db = $db;
	}

	function insertComment( &$comment ) {
		$query = "INSERT INTO comments (".
			"app_id, user_id, root_comment_id, parent_comment_id, subject,".
			"comment_date, comment, rating, constraint_level".
			") values (".
			"?, ?, ?, ?, ?, from_unixtime(?), ?, ?, ?)";
		$this->db->query( $query, [
			$comment->app_id,
			$comment->user_id,
			$comment->root_comment_id,
			$comment->parent_comment_id,
			$comment->subject,
			$comment->comment_date,
			$comment->comment,
			$comment->rating,
			$comment->constraint_level
		]);
		$comment->id = $this->db->getInsertId();
	}

	function update( $comment ) {
		$query = "UPDATE comments ".
			"SET app_id = ?, ".
			"user_id = ?, ".
			"root_comment_id = ?, ".
			"parent_comment_id = ?, ".
			"subject = ?, ".
			"comment_date = from_unixtime(?), ".
			"comment = ?, ".
			"rating = ?, ".
			"org_id = ?, ".
			"constraint_level = ? ".
			"WHERE id=?";
		$this->db->query($query, [
			$comment->app_id,
			$comment->user_id,
			$comment->root_comment_id,
			$comment->parent_comment_id,
			$comment->subject,
			$comment->comment_date,
			$comment->comment,
			$comment->rating,
			$comment->org_id,
			$comment->constraint_level,
			$comment->id
		]);
	}

	function readRatings( $app_id ) {
		$query="SELECT c.user_id, c.rating, c.comment_date ".
			"FROM comments c ".
			"INNER JOIN ( ".
			"SELECT user_id, max(id) as id  ".
			"FROM comments ".
			"WHERE rating > 0 ".
			"AND rating is not null ".
			"AND app_id=? ".
			"GROUP BY user_id, app_id".
			") c2 on c.user_id = c2.user_id and c.id = c2.id";
		$this->db->query($query, [$app_id]);
		return $this->db->getAllRows();
	}

	function readCommentsByApp( $app_id ) {
		$query = "SELECT c.id, parent_comment_id, root_comment_id, subject, rating, constraint_level, ".
		         "c.org_id, c.user_id, c.comment, unix_timestamp(comment_date) as comment_date ".
		         "FROM comments c WHERE c.app_id=? ".
		         "ORDER BY comment_date ASC";
		$this->db->query($query, [$app_id]);
		return $this->db->getAllRows();
	}

	function readLastRating( $app_id, $user_id ) {
		$query="SELECT rating from comments ".
			"WHERE user_id=? and app_id=? ".
			"ORDER by id desc LIMIT 1";
		$this->db->query($query, [$user_id, $app_id]);
		if ( $row=$this->db->nextRow() ) {
			return $row["rating"];
		} else {
			return 0;
		}
	}

	function readByID( $comment_id )  {
		$query="SELECT c.*, unix_timestamp( c.comment_date ) as unix_comment_date, ".
			"o.chapter, o.domain, o.region, o.nation ".
			"FROM comments c ".
			"LEFT JOIN organizations o ON o.id=c.org_id ".
			"WHERE c.id=?";
		$this->db->query($query, [$comment_id]);
		if( $this->db->numRows() > 0 ) {
			$commentrow=$this->db->nextRow();
			$comment = new Comment(
				$commentrow['id'],
				$commentrow['app_id'],
				$commentrow['user_id'],
				$commentrow['root_comment_id'],
				$commentrow['parent_comment_id'],
				$commentrow['subject'],
				$commentrow['comment_date'],
				$commentrow['comment'],
				$commentrow['rating'],
				$commentrow['org_id'],
				$commentrow['constraint_level']
			);
		} else {
			$comment = new Comment();
		}
		return $comment;
	}
}