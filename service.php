<?php

use Apretaste\Challenges;
use Apretaste\Level;
use Apretaste\Request;
use Apretaste\Response;
use Framework\Alert;
use Framework\Database;

class Service
{

	/**
	 * Get the list of news
	 *
	 * @param Request $request
	 * @param Response $response
	 * @throws Alert
	 * @author salvipascual
	 */
	public function _main(Request $request, Response &$response)
	{
		$selectedCategory = $request->input->data->category ?? false;
		$categoryWhere = $selectedCategory ? "WHERE A.category_id = $selectedCategory" : "";
		$articles = Database::query("SELECT A.id, A.title, A.pubDate, A.author, A.image, A.imageLink, A.description, A.comments, B.name AS category, A.tags FROM _ddc_articles A LEFT JOIN _ddc_categories B ON A.category_id = B.id $categoryWhere ORDER BY pubDate DESC LIMIT 20");

		$inCuba = $request->input->inCuba ?? false;
		$serviceImgPath = SERVICE_PATH . "ddc/images";
		$images = ["$serviceImgPath/diariodecuba-logo.png", "$serviceImgPath/no-image.png"];
		$ddcImgDir = TEMP_PATH . "/cache";

		foreach ($articles as $article) {
			$article->pubDate = self::toEspMonth(date('j F, Y', strtotime($article->pubDate)));
			$article->tags = explode(',', $article->tags);
			$article->description = quoted_printable_decode($article->description);

			if (!$inCuba) {
				$imgPath = "$ddcImgDir/{$article->image}";
				if (!file_exists($imgPath)) {
					file_put_contents($imgPath, file_get_contents($article->imageLink));
				}
				$images[] = $imgPath;
			} else {
				$article->image = "no-image.png";
			}
		}

		$content = ["articles" => $articles, "selectedCategory" => $selectedCategory];

		// send data to the view
		$response->setCache(60);
		$response->setLayout('diariodecuba.ejs');
		$response->setTemplate("stories.ejs", $content, $images);
	}

	private static function toEspMonth(String $date)
	{
		$months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
		$espMonths = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];

		return str_replace($months, $espMonths, $date);
	}

	/**
	 * Call to show the news
	 *
	 * @param Request
	 * @param Response
	 * @return Response
	 * @throws Exception
	 */
	public function _historia(Request $request, Response $response)
	{
		// get link to the article
		$id = $request->input->data->id ?? false;
		$images[] = SERVICE_PATH . "ddc/images/diariodecuba-logo.png";

		if ($id) {
			$article = Database::query("SELECT * FROM _ddc_articles WHERE id='$id'")[0];

			$article->pubDate = self::toEspMonth((date('j F, Y', strtotime($article->pubDate))));
			$article->tags = explode(',', $article->tags);
			$article->description = quoted_printable_decode($article->description);
			$article->content = quoted_printable_decode($article->content);
			$article->imageCaption = quoted_printable_decode($article->imageCaption);
			$article->comments = Database::query("SELECT A.*, B.username FROM _ddc_comments A LEFT JOIN person B ON A.id_person = B.id WHERE A.id_article='{$article->id}' ORDER BY A.id DESC");
			$article->myUsername = $request->person->username;

			foreach ($article->comments as $comment) {
				$comment->inserted = date('d/m/Y · h:i a', strtotime($comment->inserted));
			}

			// get the image if exist
			$ddcImgDir = TEMP_PATH . "/cache";
			if (!empty($article->image)) $images[] = "$ddcImgDir/{$article->image}";

			// send info to the view
			$response->setCache('30');
			$response->setLayout('diariodecuba.ejs');
			$response->setTemplate("story.ejs", $article, $images);

			Challenges::complete("read-ddc", $request->person->id);
		} else {
			return $this->error($response, "Articulo no encontrado", "No sabemos que articulo estas buscando");
		}
	}

	/**
	 * Return an error message
	 *
	 * @param Response $response
	 * @param String $title
	 * @param String $desc
	 * @return Response
	 * @throws Alert
	 * @author salvipascual
	 */
	private function error(Response $response, $title, $desc)
	{
		// display show error in the log
		error_log("[DIARIODECUBA] $title | $desc");

		// send the logo
		$images[] = SERVICE_PATH . "ddc/images/diariodecuba-logo.png";

		// return error template
		$response->setLayout('diariodecuba.ejs');
		return $response->setTemplate('message.ejs', ["header" => $title, "text" => $desc], $images);
	}

	/**
	 * Watch the last comments in articles or with no article
	 *
	 * @param Request $request
	 * @param Response $response
	 * @throws Alert
	 */

	public function _comentarios(Request $request, Response $response)
	{
		$comments = Database::query("SELECT A.*, B.username, C.title, C.pubDate, C.author FROM _ddc_comments A LEFT JOIN person B ON A.id_person = B.id LEFT JOIN _ddc_articles C ON C.id = A.id_article ORDER BY A.id DESC LIMIT 20");

		foreach ($comments as $comment) {
			$comment->inserted = date('d/m/Y · h:i a', strtotime($comment->inserted));
			$comment->pubDate = self::toEspMonth(date('j F, Y', strtotime($comment->pubDate)));
		}

		$images = [SERVICE_PATH . "ddc/images/diariodecuba-logo.png"];

		// send info to the view
		$response->setLayout('diariodecuba.ejs');
		$response->setTemplate("comments.ejs", ["comments" => $comments, "myUsername" => $request->person->username], $images);
	}

	/**
	 * Comment an article
	 *
	 * @param Request $request
	 * @param Response $response
	 *
	 * @throws Exception
	 * @author ricardo
	 *
	 */
	public function _comentar(Request $request, Response $response)
	{
		// do not allow guest comments
		if ($request->person->isGuest) {
			return;
		}

		// get comment data
		$comment = $request->input->data->comment;
		$articleId = $request->input->data->article;

		if ($articleId) {
			// check the note ID is valid
			$article = Database::query("SELECT COUNT(*) AS total FROM _ddc_articles WHERE id='$articleId'");
			if ($article[0]->total == "0") return;

			// save the comment
			$comment = Database::escape($comment, 255);
			Database::query("
				INSERT INTO _ddc_comments (id_person, id_article, content) VALUES ('{$request->person->id}', '$articleId', '$comment');
				UPDATE _ddc_articles SET comments = comments+1 WHERE id='$articleId';");

			// add the experience
			Level::setExperience('NEWS_COMMENT_FIRST_DAILY', $request->person->id);
		} else {
			Database::query("INSERT INTO _ddc_comments (id_person, content) VALUES ('{$request->person->id}', '$comment')");
		}
	}
}
