<?php

use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;

class Service
{
	/**
	 * Function executed when the service is called
	 *
	 * @author salvipascual
	 *
	 * @param Request
	 * @param Response
	 */
	public function _main(Request $request, Response &$response)
	{
  	// send data to the view
		$pathToService = Utils::getPathToService($response->serviceName);
		$response->setCache(60);
		$response->setLayout('diariodecuba.ejs');
		$response->setTemplate("allStories.ejs", $this->allStories(),["$pathToService/images/diariodecuba-logo.png"]);
		
	}
	/**
	 * Call to show the news
	 *
	 * @param Request
	 * @param Response
	 *
	 * @return void
	 */
	public function _buscar(Request $request, Response &$response)
	{
		// no allow blank entries
		if(empty($request->input->data->searchQuery))
		{

			$response->setLayout('diariodecuba.ejs');
			$response->setTemplate('text.ejs', [
				"title" => "Su busqueda parece estar en blanco",
				"body" => "debe decirnos sobre que tema desea leer"
			]);

			return;
		}

		// search by the query
		$articles = $this->searchArticles($request->input->data->searchQuery);

		// error if the search returns empty
		if(empty($articles))
		{

			$response->setLayout('diariodecuba.ejs');
			$response->setTemplate("text.ejs", [
				"title" => "Su busqueda parece estar en blanco",
				"body" => html_entity_decode("Su busqueda no gener&oacute; ning&uacute;n resultado. Por favor cambie los t&eacute;rminos de b&uacute;squeda e intente nuevamente.")
			]);

			return;
		}

		$responseContent = [
			"articles" => $articles,
			"search" => $request->input->data->searchQuery
		];
		$response->setCache(240);
		$response->setLayout('diariodecuba.ejs');
		$response->setTemplate("searchArticles.ejs", $responseContent);
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
		$link = $request->input->data->query;

		// no allow blank entries
		if(empty($link))
		{
			$response->setLayout('diariodecuba.ejs');
			$response->setTemplate("text.ejs", [
				"title" => "Su busqueda parece estar en blanco",
				"body" => "Su busqueda parece estar en blanco, debe decirnos que articulo quiere leer"
			]);

			return;
		}

		// send the actual response
		$responseContent = $this->story($link);

		// get the image if exist
		$images = [];
		if( ! empty($responseContent['img']))
		{
			$images = [$responseContent['img']];
		}

		if(isset($request->input->data->search)){
			$isCategory = $request->input->data->isCategory == "true";
			$type = $isCategory ? "CATEGORIA" : "BUSCAR";
			$param = $isCategory ? "query" : "searchQuery";
			$responseContent['backButton'] = "{'command':'DIARIODECUBA $type', 'data':{'$param':'{$request->input->data->search}'}}";
		}
		else $responseContent['backButton'] = "{'command':'DIARIODECUBA'}";

		$response->setCache();
		$response->setLayout('diariodecuba.ejs');
		$response->setTemplate("story.ejs", $responseContent, $images);
	}

	/**
	 * Call list by categoria
	 *
	 * @param Request
	 * @param Response
	 *
	 * @return void
	 */
	public function _categoria(Request $request, Response &$response)
	{
		// get the current category
		$category = $request->input->data->query;
		$caption = $request->input->data->caption;

		// do not allow empty categories
		if(empty($category))
		{
			$response->setTemplate('message.ejs', [
				"header" => "Búsqueda en blanco",
				"icon" => "sentiment_very_dissatisfied",
				"text" => "Su búsqueda parece estar en blanco, debe decirnos sobre que categoría desea leer.",
				"button" => ["href" => "DIARIODECUBA", "caption" => "Noticias"]
			]);

			return;
		}

		$content = [
			"articles" => $this->listArticles($category),
			"category" => $caption
		];
		$response->setCache(240);
		$response->setLayout('diariodecuba.ejs');
		$response->setTemplate("searchArticles.ejs", $content);
	}

	/**
	 * Search stories
	 *
	 * @param String
	 *
	 * @return array
	 */
	private function searchArticles($query)
	{
		// load from cache file if exists
		$temp = Utils::getTempDir();
		$fileName = date("YmdH") . md5($query) . 'search_diariodecuba.tmp';
		$fullPath = "$temp/$fileName";

		$articles = false;

		if(file_exists($fullPath)) $articles = @unserialize(file_get_contents($fullPath));

		if(!is_array($articles)){
			// Setup crawler
			$client = new Client();
			$articles = [];
			for ($i=0; $i < 2; $i++) {
				$url = "http://www.diariodecuba.com/search/node/" . urlencode($query)."?page=$i";
				$crawler = $client->request('GET', $url);
				
				$crawler->filter('div.search-result')->each(function($item) use (&$articles, $temp, $client){
					if($item->filter('.audio-watermark, .video-watermark')->count()==0){
						$link = $item->filter('h1.search-title > a')->attr("href");
						$title = $item->filter('h1.search-title > a')->text();

						$tmpFile = "$temp/article_".md5($title)."_ddc.html";

						if(!file_exists($tmpFile)){
							$html = file_get_contents($link);
							file_put_contents($tmpFile, $html);
						}

						$pubDate = strtotime((new Crawler(file_get_contents($tmpFile)))->filter('meta[itemprop="datePublished"]')->attr('content'));

						$articles[] = [
							"description" => $item->filter('p.search-snippet')->text(),
							"title" => $title,
							"link" => str_replace('http://www.diariodecuba.com/', "", $link),
							"pubDate" => $pubDate
						];
					}
				});
			}

			usort($articles, function($a, $b){
				return ($b["pubDate"]-$a["pubDate"]);
			});

			setlocale(LC_ALL, 'es_ES.UTF-8');

			array_walk($articles, function(&$value){
				$value["pubDate"] = strftime("%B %d, %Y.", $value["pubDate"])." ".date('h:i a', $value["pubDate"]);
			});

			file_put_contents($fullPath, serialize($articles));
		}

		return $articles;
	}

	/**
	 * Get the array of news by content
	 *
	 * @param String
	 *
	 * @return array
	 */
	private function listArticles($query)
	{
		// load from cache file if exists
		$temp = Utils::getTempDir();
		$fileName = date("YmdH") . md5($query) . '.tmp';
		$fullPath = "$temp/$fileName";

		$articles = false;

		/*if(file_exists($fullPath))
		{
			$articles = @unserialize(file_get_contents($fullPath));
		}*/

		if( ! is_array($articles))
		{
			// Setup crawler
			$client = new Client();
			$crawler = $client->request('GET', "http://www.diariodecuba.com/etiquetas/$query.html");

			// Collect articles by category
			$articles = [];
			$crawler->filter('.views-row')->each(function($item, $i) use (&$articles, $temp){
				$link = $item->filter('.views-field-title span > a')->attr("href");
				$title = $item->filter('.views-field-title span > a')->text();

				$tmpFile = "$temp/article_".md5($title)."_ddc.html";

				if(!file_exists($tmpFile)){
					$html = file_get_contents("http://www.diariodecuba.com$link");
					file_put_contents($tmpFile, $html);
				}

				$pubDate = strtotime((new Crawler(file_get_contents($tmpFile)))->filter('meta[itemprop="datePublished"]')->attr('content'));

				
				if($item->filter('.audio-watermark, .video-watermark, .photo-watermark')->count()==0)
				
				$articles[] = [
					"description" => $item->filter('.views-field-field-summary-value p')->text(),
					"title" => $title,
					"link" => $link,
					"pubDate" => $pubDate
				];
			});

			usort($articles, function($a, $b){
				return ($b["pubDate"]-$a["pubDate"]);
			});

			setlocale(LC_ALL, 'es_ES.UTF-8');

			array_walk($articles, function(&$value){
				$value["pubDate"] = strftime("%B %d, %Y.", $value["pubDate"])." ".date('h:i a', $value["pubDate"]);
			});

			file_put_contents($fullPath, serialize($articles));
		}

		return $articles;
	}

	/**
	 * Get an specific news to display
	 *
	 * @param String
	 *
	 * @return array
	 */
	private function story($query)
	{
		$cacheFile = Utils::getTempDir(). md5($query) . date("YmdH") . 'story_diariodecuba.tmp';
		$notice = false;

		if(file_exists($cacheFile)) $notice = @unserialize(file_get_contents($cacheFile));

		if(!is_array($notice)){
		
		// create a new client
		$client = new Client();
		$guzzle = $client->getClient();
		$client->setClient($guzzle);

		// create a crawler
		$crawler = $client->request('GET', "http://www.diariodecuba.com/$query");

		// search for title
		$title = $crawler->filter('h1.title')->text();
    	//$pubDate = $crawler->filter('pubDate')->text();

		// get the intro

		$titleObj = $crawler->filter('div.content:nth-child(1) p:nth-child(1)');
		$intro = $titleObj->count() > 0 ? $titleObj->text() : "";

		// get the images
		$imageObj = $crawler->filter('figure.field-field-image .leading_image img');
		$imgUrl = "";
		$imgAlt = "";
		$img = "";
		if($imageObj->count() != 0)
		{
			$imgUrl = trim($imageObj->attr("src"));
			$imgAlt = trim($imageObj->attr("alt"));

			// get the image
			if( ! empty($imgUrl))
			{
				$imgName = Utils::generateRandomHash() . "." . pathinfo($imgUrl, PATHINFO_EXTENSION);
				$img = \Phalcon\DI\FactoryDefault::getDefault()->get('path')['root'] . "/temp/$imgName";
				file_put_contents($img, file_get_contents($imgUrl));
			}
		}

		// get the array of paragraphs of the body
		$paragraphs = $crawler->filter('div.node div.content p');
		$content = [];
		foreach($paragraphs as $p)
		{
			$content[] = trim($p->textContent);
		}

		// create a json object to send to the template
		$notice = [
			"title" => $title,
			"intro" => $intro,
			"img" => $img,
			"imgAlt" => $imgAlt,
			"content" => $content,
    		//"pubDate" => $pubDate,
			"url" => "http://www.diariodecuba.com/$query"
		];
		file_put_contents($cacheFile, serialize($notice));
	}
		return $notice;
	}

	/**
	 * Get the link to the news starting from the /content part
	 *
	 * @param String
	 *
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
	
	private function allStories()
	{
	// load from cache file if exists
    $cacheFile = Utils::getTempDir(). date("YmdH") . 'main_diariodecuba.tmp';
	$articles = false;

    if(file_exists($cacheFile)) $articles = @unserialize(@file_get_contents($cacheFile));

    if (!is_array($articles))
    {
      // create a new client
      $client = new Client();
      $guzzle = $client->getClient();
      $client->setClient($guzzle);

      // create a crawler
      $crawler = $client->request('GET', "http://www.diariodecuba.com/rss.xml");

      // get all articles
      $articles = [];
      $crawler->filter('channel item')->each(function($item, $i) use (&$articles)
      {
        // get all parameters
        $title = $item->filter('title')->text();
        $link = $this->urlSplit($item->filter('link')->text());
        $description = $item->filter('description')->text();
        $description = trim(strip_tags($description));
        $description = html_entity_decode($description);
				$pubDate = $item->filter('pubDate')->text();
				$pubDate = $item->filter('pubDate')->text();
				setlocale(LC_ALL, 'es_ES.UTF-8');
				$fecha = strftime("%B %d, %Y.",strtotime($pubDate)); 
				$hora = date_format((new DateTime($pubDate)),'h:i a');
				$pubDate = $fecha." ".$hora;
        $category = $item->filter('category')->each(function($category, $j){
					$catLink = $category->attr('domain');
					$catLink = rtrim(explode("etiquetas/", $catLink)[1], ".html");
					$catCaption = $category->text();
					return ["caption" => $catCaption, "link" => $catLink];
				});

        if($item->filter('dc|creator')->count() > 0){
          $author = trim($item->filter('dc|creator')->text());
        }
				
				if(strpos($author, "DDC TV") === false)
					$articles[] = [
						"title" => $title,
						"link" => $link,
						"pubDate" => $pubDate,
						"description" => $description,
						"category" => $category,
						"author" => isset($author) ? $author : ""
					];
      });

      // save cache in the temp folder
      file_put_contents($cacheFile, serialize($articles));
		}
		return array("articles" => $articles);
		
	}
	/**
	 * Return a generic error email, usually for try...catch blocks
	 *
	 * @author salvipascual
	 * @author kumahacker
	 *
	 * @param Response
	 * @param Exception
   *
	 * @return void
	 */
	private function respondWithError(Response &$response, Exception $e)
	{
		error_log("WARNING: ERROR ON SERVICE DIARIO DE CUBA");

		$response->setLayout('diariodecuba.ejs');
		$response->setTemplate("text.ejs", [
			"title" => "Error inesperado",
			"body" => html_entity_decode("Lo siento pero hemos tenido un error inesperado. Enviamos una peticion para corregirlo. 
                 Por favor intente nuevamente mas tarde. 
                 Informaci&oacute;n t&eacute;cnica: {$e->getFile()} {$e->getLine()}: {$e->getMessage()}")
		]);
	}
}
