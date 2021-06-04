<?php

use Apretaste\Bucket;
use Apretaste\Request;
use Apretaste\Response;
use Apretaste\Database;

class Service
{
	/**
	 * Default service
	 *
	 * @param Request $request
	 * @param Response $response
	 * @author salvipascual
	 */
	public function _main(Request $request, Response $response)
	{
		return $this->_titulares($request, $response);
	}

	/**
	 * Get the list of news
	 *
	 * @param Request $request
	 * @param Response $response
	 * @throws \Apretaste\Alert
	 * @throws \Framework\Alert
	 * @author salvipascual
	 */
	public function _titulares(Request $request, Response $response)
	{
		// get the articles
		$articles = Database::queryCache("
			SELECT id, image, imageCaption, title, author, description
			FROM _news_articles
			WHERE media_id = 1
			ORDER BY pubDate DESC 
			LIMIT 20");

		// error if data could not be found
		if (empty($articles)) {
			$response->setTemplate('message.ejs');
			return;
		}

		// array of images to send to the view
		$images = [];

		// for all articles ...
		foreach ($articles as $item) {
			// decode the strings
			$item->title = quoted_printable_decode($item->title);
			$item->imageCaption = quoted_printable_decode($item->imageCaption);
			$item->description = quoted_printable_decode($item->description);

			// create path to the image
			$imgPath = Bucket::get('ddc', $item->image);

			// set the right image
			if (file_exists($imgPath)) $images[] = $imgPath;
			else $item->image = false;
		}

		// send data to the view
		$response->setCache('hour');
		$response->setTemplate("titulares.ejs", ["articles" => $articles], $images);
	}

	/**
	 * Call to show the news
	 *
	 * @param Request $request
	 * @param Response $response
	 * @author salvipascual
	 */
	public function _historia(Request $request, Response $response)
	{
		// get link to the article
		$id = $request->input->data->id ?? false;

		// get article from the database
		$article = Database::queryCache("
			SELECT title, author, description, image, imageCaption, content
			FROM _news_articles
			WHERE id = '$id'")[0];

		// error if data could not be found
		if (empty($article)) {
			return $response->setTemplate('message.ejs');
		}

		// decode the strings
		$article->title = quoted_printable_decode($article->title);
		$article->description = quoted_printable_decode($article->description);
		$article->content = quoted_printable_decode($article->content);
		$article->imageCaption = quoted_printable_decode($article->imageCaption);

		// get path to the image
		$imgPath = Bucket::get('ddc', $article->image);

		// set the right image
		$images = [];
		if (file_exists($imgPath)) $images[] = $imgPath;
		else $article->image = false;

		// send info to the view
		$response->setCache('year');
		$response->setTemplate("historia.ejs", $article, $images);
	}
}
