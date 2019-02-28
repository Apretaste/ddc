<?php
use Goutte\Client;

class Service
{
	/**
	 * Function executed when the service is called
	 *
	 * @author salvipascual
	 * @param Request
	 * @param Response
	 */
	public function _main(Request $request, Response $response)
	{
		// get stories
		$stories = $this->allStories();

		// send data to the view 
//		$response->setLayout('diariodecuba.ejs');
		$response->setTemplate("allStories.ejs", ["articles" => $stories]);
	}

	/**
	 * Call to show the news
	 *
	 * @param Request
	 * @return Response
	 */
	public function _buscar(Request $request, Response $response)
	{
		// no allow blank entries
		if (empty($request->query)) {
//			$response->setLayout('diariodecuba.ejs');
			$response->createFromText("Su busqueda parece estar en blanco, debe decirnos sobre que tema desea leer");
		}

		// search by the query
		try {
			$articles = $this->searchArticles($request->query);
		} catch (Exception $e) {
			return $this->respondWithError();
		}

		// error if the search returns empty
		if(empty($articles)) {
//			$response->setLayout('diariodecuba.ejs');
			$response->createFromText("Su busqueda <b>{$request->query}</b> no generó ningún resultado. Por favor cambie los términos de búsqueda e intente nuevamente.");
		}

		$content = [
			"articles" => $articles,
			"search" => $request->query
		];

//		$response->setLayout('diariodecuba.ejs');
		$response->setTemplate("searchArticles.ejs", $content);
	}

	/**
	 * Call to show the news
	 *
	 * @param Request
	 * @param Response
	 */
	public function _historia(Request $request, Response $response)
	{
		// get link to the article
		$link = $request->input->data->link;

		// no allow blank entries
		if (empty($link)) {
			$response->createFromText("Su busqueda parece estar en blanco, debe decirnos que articulo quiere leer");
		}

		// send the actual response
		try {
			$responseContent = $this->story($link);
		} catch (Exception $e) {
			return $this->respondWithError();
		}

		// get the image if exist
		$images = [];
		if( ! empty($responseContent['img'])) {
			$images = array($responseContent['img']);
		}

		$response->setCache();
//		$response->setLayout('diariodecuba.ejs');
		$response->setTemplate("story.ejs", $responseContent, $images);
	}

	/**
	 * Call list by categoria
	 *
	 * @param Request
	 * @return Response
	 */
	public function _categoria(Request $request, Response $response)
	{
		// get the current category
		$category = $request->input->data->category;

		// do not allow empty categories
		if (empty($category)) {
			return $response->setTemplate('message.ejs', [
				"header"=>"Búsqueda en blanco",
				"icon"=>"sentiment_very_dissatisfied",
				"text" => "Su búsqueda parece estar en blanco, debe decirnos sobre que categoría desea leer.",
				"button" => ["href"=>"DIARIODECUBA", "caption"=>"Noticias"]
			]);
		}

		$content = [
			"articles" => $this->listArticles($category),
			"category" => $category
		];

//		$response->setLayout('diariodecuba.ejs');
		$response->setTemplate("catArticles.ejs", $content);
	}

	/**
	 * Search stories
	 *
	 * @param String
	 * @return Array
	 */
	private function searchArticles($query)
	{
		// load from cache file if exists 
		$temp = Utils::getTempDir();
		$fileName = date("YmdH");
die($fileName);

		// Setup crawler
		$client = new Client();
		$url = "http://www.diariodecuba.com/search/node/".urlencode($query);
		$crawler = $client->request('GET', $url);


//		file_put_contents("$temp/", data);

		// Collect saearch by term
		$articles = [];
		$crawler->filter('div.search-result')->each(function($item) use (&$articles) {
			$articles[] = [
				"description" => $item->filter('p.search-snippet')->text(),
				"title"	=> $item->filter('h1.search-title > a')->text(),
				"link" => str_replace('http://www.diariodecuba.com/', "", $item->filter('h1.search-title > a')->attr("href"))
			];			   
		});

		return $articles;
	}

	/**
	 * Get the array of news by content
	 *
	 * @param String
	 * @return Array
	 */
	private function listArticles($query)
	{
		// Setup crawler
		$client = new Client();
		$crawler = $client->request('GET', "http://www.diariodecuba.com/rss.xml");

		// Collect articles by category
		$articles = [];
		$crawler->filter('channel item')->each(function($item, $i) use (&$articles, $query) {
			// if category matches, add to list of articles
			$item->filter('category')->each(function($cat, $i) use (&$articles, $query, $item) {
				if (strtoupper($cat->text()) == strtoupper($query)) {
					// $title = $item->filter('title')->text();
					// $link = $this->urlSplit($item->filter('link')->text());
					// $pubDate = $item->filter('pubDate')->text();
					// $description = $item->filter('description')->text();
					// $cadenaAborrar = "/<!-- google_ad_section_start --><!-- google_ad_section_end --><p>/";
					// $description = preg_replace($cadenaAborrar, '', $description);
					// $description = preg_replace("/<\/?a[^>]*>/", '', $description);//quitamos las <a></a>
					// $description = preg_replace("/<\/?p[^>]*>/", '', $description);//quitamos las <p></p>

					$title = $item->filter('title')->text();
					$link = $this->urlSplit($item->filter('link')->text());
					$description = $item->filter('description')->text();
					$description = trim(strip_tags($description));
					$description = html_entity_decode($description);
					$description = substr($description, 0, 200)." ...";

					$author = "desconocido";
					if ($item->filter('dc|creator')->count() > 0) {
						$authorString = trim($item->filter('dc|creator')->text());
						$author = "{$authorString}";
					}

					$articles[] = [
						"title" => $title,
						"link" => $link,
						"pubDate" => $pubDate,
						"description" => $description,
						"author" => $author
					];
				}
			});
		});

		return $articles;
	}

	/**
	 * Get all stories from a query
	 *
	 * @return Array
	 */
	private function allStories()
	{
		// load from cache file if exists 
		$cacheFile = Utils::getTempDir() . date("YmdH") . 'diariodecuba.tmp';
		if(file_exists($cacheFile)) $articles = unserialize(file_get_contents($cacheFile));
		else {
			// create a new client
			$client = new Client();
			$guzzle = $client->getClient();
			$client->setClient($guzzle);

			// create a crawler
			$crawler = $client->request('GET', "http://www.diariodecuba.com/rss.xml");

			// get all articles
			$articles = [];
			$crawler->filter('channel item')->each(function($item, $i) use (&$articles) {
				// get all parameters
				$title = $item->filter('title')->text();
				$link = $this->urlSplit($item->filter('link')->text());
				$description = $item->filter('description')->text();
				$description = trim(strip_tags($description));
				$description = html_entity_decode($description);
				$description = substr($description, 0, 200)." ...";
				$pubDate = $item->filter('pubDate')->text();
				$category = $item->filter('category')->each(function($category, $j) {return $category->text();});

				if ($item->filter('dc|creator')->count() == 0) $author = "desconocido";
				else {
					$authorString = trim($item->filter('dc|creator')->text());
					$author = "{$authorString}";
				}

				$categoryLink = [];
				foreach ($category as $currCategory) {
					$categoryLink[] = $currCategory;
				}

				$articles[] = [
					"title" => $title,
					"link" => $link,
					"pubDate" => $pubDate,
					"description" => $description,
					"category" => $category,
					"categoryLink" => $categoryLink,
					"author" => $author
				];
			});

			// save cache in the temp folder
			file_put_contents($cacheFile, serialize($articles));
		}

		// return response content
		return $articles;
	}

	/**
	 * Get an specific news to display
	 *
	 * @param String
	 * @return Array
	 */
	private function story($query)
	{
		// create a new client
		$client = new Client();
		$guzzle = $client->getClient();
		$client->setClient($guzzle);

		// create a crawler
		$crawler = $client->request('GET', "http://www.diariodecuba.com/$query");

		// search for title
		$title = $crawler->filter('h1.title')->text();

		// get the intro

		$titleObj = $crawler->filter('div.content:nth-child(1) p:nth-child(1)');
		$intro = $titleObj->count()>0 ? $titleObj->text() : "";

		// get the images
		$imageObj = $crawler->filter('figure.field-field-image-content img');
		$imgUrl = ""; $imgAlt = ""; $img = "";
		if ($imageObj->count() != 0)
		{
			$imgUrl = trim($imageObj->attr("src"));
			$imgAlt = trim($imageObj->attr("alt"));

			// get the image
			if ( ! empty($imgUrl))
			{
				$imgName = $this->utils->generateRandomHash() . "." . pathinfo($imgUrl, PATHINFO_EXTENSION);
				$img = \Phalcon\DI\FactoryDefault::getDefault()->get('path')['root'] . "/temp/$imgName";
				file_put_contents($img, file_get_contents($imgUrl));
			}
		}

		// get the array of paragraphs of the body
		$paragraphs = $crawler->filter('div.node div.content p');
		$content = [];
		foreach ($paragraphs as $p)
		{
			$content[] = trim($p->textContent);
		}

		// create a json object to send to the template
		return array(
			"title" => $title,
			"intro" => $intro,
			"img" => $img,
			"imgAlt" => $imgAlt,
			"content" => $content,
			"url" => "http://www.diariodecuba.com/$query"
		);
	}

	/**
	 * Get the link to the news starting from the /content part
	 *
	 * @param String
	 * @return String
	 * http://www.martinoticias.com/content/blah
	 */
	private function urlSplit($url)
	{
		$url = explode("/", trim($url));
		unset($url[0]);
		unset($url[1]);
		unset($url[2]);
		return implode("/", $url);
	}

	/**
	 * Return a generic error email, usually for try...catch blocks
	 *
	 * @auhor salvipascual
	 * @return Respose
	 */
	private function respondWithError()
	{
		error_log("WARNING: ERROR ON SERVICE DIARIO DE CUBA");

//		$response->setLayout('diariodecuba.ejs');
		$response->createFromText("Lo siento pero hemos tenido un error inesperado. Enviamos una peticion para corregirlo. Por favor intente nuevamente mas tarde.");
	}
}
