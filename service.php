<?php

use Apretaste\Core;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;

class Service
{

	/**
	 * Get the list of news
	 *
	 * @param Request
	 * @param Response
	 * @throws FeedException
	 * @author salvipascual
	 */
	public function _main(Request $request, Response &$response)
	{
		$selectedCategory = $request->input->data->category ?? false;
		$categoryWhere = $selectedCategory ? "WHERE A.category_id = $selectedCategory" : "";
		$articles = q("SELECT A.id, A.title, A.pubDate, A.author, A.image, A.description, A.comments, B.name AS category, A.tags FROM ddc_articles A LEFT JOIN ddc_categories B ON A.category_id = B.id $categoryWhere ORDER BY pubDate DESC LIMIT 20");

		$inCuba = $request->input->inCuba ?? false;
		$serviceImgPath = Utils::getPathToService("ddc") . "/images";
		$images = ["$serviceImgPath/diariodecuba-logo.png", "$serviceImgPath/no-image.png"];

		foreach ($articles as $article) {
			$article->pubDate = self::toEspMonth(date('j F, Y', strtotime($article->pubDate)));
			$article->tags = explode(',', $article->tags);
			if (!$inCuba) $images[] = Core::getTempDir() . "/{$article->image}";
			else $article->image = "no-image.png";
		}

		$content = ["articles" => $articles, "selectedCategory" => $selectedCategory];

		// send data to the view
		$response->setCache(60);
		$response->setLayout('diariodecuba.ejs');
		$response->setTemplate("stories.ejs", $content, $images);
	}

	/**
	 * Call to show the news
	 *
	 * @param Request
	 * @param Response
	 * @throws Exception
	 */
	public function _historia(Request $request, Response $response)
	{
		// get link to the article
		$id = $request->input->data->id;
		$article = q("SELECT * FROM ddc_articles WHERE id='$id'")[0];

		$article->pubDate = self::toEspMonth((date('j F, Y', strtotime($article->pubDate))));
		$article->tags = explode(',', $article->tags);
		$article->comments = q("SELECT A.*, B.username FROM ddc_comments A LEFT JOIN person B ON A.id_person = B.id WHERE A.id_article='{$article->id}' ORDER BY A.id DESC");
		$article->myUsername = $request->person->username;

		foreach ($article->comments as $comment) $comment->inserted = date('d/m/Y · h:i a', strtotime($comment->inserted));

		// get the image if exist
		$images = empty($article->image) ? [] : [Core::getTempDir() . "/{$article->image}"];

		$images[] = Utils::getPathToService("ddc") . "/images/diariodecuba-logo.png";

		// send info to the view
		$response->setCache('30');
		$response->setLayout('diariodecuba.ejs');
		$response->setTemplate("story.ejs", $article, $images);

		Challenges::complete("read-ddc", $request->person->id);
	}

	/**
	 * Watch the last comments in articles or with no article
	 *
	 * @param Request $request
	 * @param Response $response
	 */

	public function _comentarios(Request $request, Response $response)
	{
		$comments = q("SELECT A.*, B.username, C.title, C.pubDate, C.author FROM ddc_comments A LEFT JOIN person B ON A.id_person = B.id LEFT JOIN ddc_articles C ON C.id = A.id_article ORDER BY A.id DESC LIMIT 20");

		foreach ($comments as $comment) {
			$comment->inserted = date('d/m/Y · h:i a', strtotime($comment->inserted));
			$comment->pubDate = self::toEspMonth(date('j F, Y', strtotime($comment->pubDate)));
		}

		$images = [Utils::getPathToService("ddc") . "/images/diariodecuba-logo.png"];

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
		if ($request->person->email === 'guest') return;

		$comment = $request->input->data->comment;;
		$articleId = $request->input->data->article;

		if ($articleId) {
			// check the note ID is valid
			$article = q("SELECT COUNT(*) AS total FROM ddc_articles WHERE id='$articleId'");
			if ($article[0]->total == "0") return;

			// save the comment
			$comment = e($comment, 255);
			q("
			INSERT INTO ddc_comments (id_person, id_article, content) VALUES ('{$request->person->id}', '$articleId', '$comment');
			UPDATE ddc_articles SET comments = comments+1 WHERE id='$articleId';
		");

			// add the experience
			Level::setExperience('NEWS_COMMENT_FIRST_DAILY', $request->person->id);
		} else {
			q("INSERT INTO ddc_comments (id_person, content) VALUES ('{$request->person->id}', '$comment')");
		}
	}

	/**
	 * Return an error message
	 *
	 * @param Response $response
	 * @param String $title
	 * @param String $desc
	 * @return Response
	 * @author salvipascual
	 */
	private function error(Response $response, $title, $desc)
	{
		// display show error in the log
		error_log("[DIARIODECUBA] $title | $desc");

		// return error template
		$response->setLayout('diariodecuba.ejs');
		return $response->setTemplate('message.ejs', ["header" => $title, "text" => $desc]);
	}

	private static function toEspMonth(String $date)
	{
		$months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
		$espMonths = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];

		return str_replace($months, $espMonths, $date);
	}
}
