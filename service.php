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
		$articles = Database::query(
			"
			SELECT 
				A.id, A.title, A.pubDate, A.author, A.image, A.imageCaption, A.imageLink, A.media_id,
				A.description, A.comments, A.tags, B.caption AS categoryCaption
			FROM _news_articles A 
			LEFT JOIN _news_categories B ON A.media_id = B.id 
			WHERE A.media_id = 1
			ORDER BY pubDate DESC 
			LIMIT 20"
		);

		$serviceImgPath = SERVICE_PATH . "ddc/images";
		$images = ["$serviceImgPath/diariodecuba-logo.png", "$serviceImgPath/no-image.png"];
		$ddcImgDir = SHARED_PUBLIC_PATH . "content/news/ddc/images";

		foreach ($articles as $article) {
			$article->title = quoted_printable_decode($article->title);
			$article->imageCaption = quoted_printable_decode($article->imageCaption);
			$article->pubDate = self::toEspMonth(date('j F, Y', strtotime($article->pubDate)));
			$article->tags = explode(',', $article->tags);
			$article->description = quoted_printable_decode($article->description);

			$imgPath = "$ddcImgDir/{$article->image}";

			$image = '';
			if (file_exists($imgPath)) {
				$image = file_get_contents($imgPath);
			}

			if (!empty($image)) {
				$images[] = $imgPath;
			} else {
				$article->image = "no-image.png";
			}

		}

		// send data to the view
		$response->setCache(60);
		$response->setLayout('diariodecuba.ejs');
		$response->setTemplate("stories.ejs", ["articles" => $articles], $images);
	}

	private static function toEspMonth(string $date)
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
			$article = Database::queryCache("
			SELECT 
				A.id, A.title, A.pubDate, A.author, A.description, A.media_id, 
				A.category_id, A.image, A.imageCaption, A.content, A.tags,
				B.caption AS source, B.name AS mediaName, C.caption AS categoryCaption
			FROM _news_articles A
			LEFT JOIN _news_media B ON A.media_id = B.id 
			LEFT JOIN _news_categories C ON A.category_id = C.id 
			WHERE A.id = '$id'")[0];

			$article->title = quoted_printable_decode($article->title);
			$article->pubDate = self::toEspMonth((date('j F, Y', strtotime($article->pubDate))));
			$article->tags = explode(',', $article->tags);
			$article->description = quoted_printable_decode($article->description);
			$article->content = quoted_printable_decode($article->content);
			$article->imageCaption = quoted_printable_decode($article->imageCaption);
			$article->myUsername = $request->person->username;

			// get the image if exist
			$ddcImgDir = SHARED_PUBLIC_PATH . "content/news/ddc/images";
			if (!empty($article->image)) {
				$images[] = "$ddcImgDir/{$article->image}";
			}

			// complete the challenge
			Challenges::complete("read-ddc", $request->person->id);

			// send info to the view
			$response->setCache('30');
			$response->setLayout('diariodecuba.ejs');
			$response->setTemplate("story.ejs", $article, $images);
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
}
