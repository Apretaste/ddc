<?php

use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;

class Service
{
	/**
	 * Get the list of news
	 *
	 * @author salvipascual
	 * @param Request
	 * @param Response
	 */
	public function _main(Request $request, Response &$response)
	{
		// try to get articles from the cache
		$articles = false;
		$cacheFile = Utils::getTempDir(). date("YmdH") . '_ddc_news.tmp';
		if(file_exists($cacheFile)) $articles = @unserialize(@file_get_contents($cacheFile));

		// if not in cache, get from DDC website
		if (!is_array($articles)) {
			// create a new client
			$client = new Client();
			$guzzle = $client->getClient();
			$client->setClient($guzzle);
			$crawler = $client->request('GET', "http://www.diariodecuba.com/rss.xml");

			// get all articles
			$articles = [];
			$crawler->filter('channel item')->each(function($item, $i) use (&$articles) {
				// get all parameters
				$title = str_replace("'", "", $item->filter('title')->text());
				$link = $item->filter('link')->text();
				$link = str_replace('http://www.diariodecuba.com/', "", $link);
				$description = $item->filter('description')->text();
				$description = trim(strip_tags($description));
				$description = html_entity_decode($description);
				$description = php::truncate($description, 160);
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

				if(strpos($author, "DDC TV") === false) {
					$articles[] = [
						"title" => $title,
						"link" => $link,
						"pubDate" => $pubDate,
						"description" => $description,
						"category" => $category,
						"author" => isset($author) ? $author : ""];
				}
			});

			// save cache in the temp folder
			file_put_contents($cacheFile, serialize($articles));
		}

		// send data to the view
		$response->setCache(60);
		$response->setLayout('diariodecuba.ejs');
		$response->setTemplate("stories.ejs", ["articles" => $articles], [Utils::getPathToService("diariodecuba")."/images/diariodecuba-logo.png"]);
	}

	/**
	 * Search the news for a term 
	 *
	 * @author salvipascual
	 * @param Request
	 * @param Response
	 */
	public function _buscar(Request $request, Response &$response)
	{
		// do no allow empty entries
		if (empty($request->input->data->query)) {
			return $this->error($response, "¿Qué desea buscar?", "Parece que está intentando realizar una búsqueda, pero no nos ha dicho que desea buscar. Regrese a la lista de noticias y escriba un término a buscar.");
		}

		// load from cache file if exists
		$articles = false;
		$query = $request->input->data->query;
		$cleanQuery = preg_replace('/[^A-Za-z0-9]/', '', $query);
		$fullPath = Utils::getTempDir() . date("Ymd") . md5($cleanQuery) . '_ddc_search.tmp';
		if(file_exists($fullPath)) $articles = @unserialize(file_get_contents($fullPath));

		// if cache do not exist, load from DDC
		if(!is_array($articles)) {
			// Setup crawler
			$client = new Client();
			$crawler = $client->request('GET', "http://www.diariodecuba.com/search/node/".urlencode($query)."?page=0");

			// Collect articles by category
			$articles = [];
			$crawler->filter('div.search-result')->each(function($item) use (&$articles){
				try {
					$link = $item->filter('h1.search-title > a')->attr("href");
					$link = str_replace('http://www.diariodecuba.com/', "", $link);
					$title = str_replace("'", "", $item->filter('h1.search-title > a')->text());
					$description = $item->filter('p.search-snippet')->text();
					$description = php::truncate($description, 160);
				} catch(Exception $e) {
					return;
				}

				// add to the list of articles
				$articles[] = [
					"title" => $title,
					"link" => $link,
					"description" => $description
				];
			});

			// save to cache
			setlocale(LC_ALL, 'es_ES.UTF-8');
			file_put_contents($fullPath, serialize($articles));
		}

		// in case no results were found
		if(empty($articles)) {
			return $this->error($response, "No hay resultados", "Su búsqueda no generó ningún resultado. Por favor cambie los términos de búsqueda e intente nuevamente.");
		}

		// send data to the template
		$response->setCache(240);
		$response->setLayout('diariodecuba.ejs');
		$response->setTemplate("search.ejs", ["articles" => $articles, "caption" => $query], [Utils::getPathToService("diariodecuba")."/images/diariodecuba-logo.png"]);
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
		$cleanLink = preg_replace('/[^A-Za-z0-9]/', '', $link);

		// try to load story from the cache
		$notice = false;
		$cacheFile = Utils::getTempDir() . md5($cleanLink) . '_ddc_story.tmp';
		if(file_exists($cacheFile)) $notice = @unserialize(file_get_contents($cacheFile));

		// if no cache, get from DDC
		if(!is_array($notice)){
			// create a new client
			$client = new Client();
			$guzzle = $client->getClient();
			$client->setClient($guzzle);

			// create a crawler
			$crawler = $client->request('GET', "http://www.diariodecuba.com/$link");

			// search for title
			$title = $crawler->filter('h1.title')->text();

			// get the intro
			$titleObj = $crawler->filter('div.content:nth-child(1) p:nth-child(1)');
			$intro = $titleObj->count() > 0 ? $titleObj->text() : "";

			// get the images
			$imageObj = $crawler->filter('figure.field-field-image .leading_image img');
			$imgUrl = "";
			$imgAlt = "";
			$img = "";
			if($imageObj->count() != 0) {
				$imgUrl = trim($imageObj->attr("src"));
				$imgAlt = trim($imageObj->attr("alt"));

				// get the image
				if( ! empty($imgUrl)) {
					$imgName = Utils::generateRandomHash() . "." . pathinfo($imgUrl, PATHINFO_EXTENSION);
					$img = \Phalcon\DI\FactoryDefault::getDefault()->get('path')['root'] . "/temp/$imgName";
					file_put_contents($img, file_get_contents($imgUrl));
				}
			}

			// get the array of paragraphs of the body
			$paragraphs = $crawler->filter('div.node div.content p');
			$content = [];
			foreach($paragraphs as $p) {
				$content[] = trim($p->textContent);
			}

			// create a json object to send to the template
			$notice = [
				"title" => $title,
				"intro" => $intro,
				"img" => $img,
				"imgAlt" => $imgAlt,
				"content" => $content
			];

			// save cache
			file_put_contents($cacheFile, serialize($notice));
		}

		// get the image if exist
		$images = empty($notice['img']) ? [] : [$notice['img']];
		$notice['img'] = basename($notice['img']);

		$images[] = Utils::getPathToService("diariodecuba")."/images/diariodecuba-logo.png";

		// send info to the view
		$response->setCache();
		$response->setLayout('diariodecuba.ejs');
		$response->setTemplate("story.ejs", $notice, $images);
	}

	/**
	 * Call list by categoria
	 *
	 * @author salvipascual
	 * @param Request
	 * @param Response
	 */
	public function _categoria(Request $request, Response &$response)
	{
		// get the current category
		$category = $request->input->data->query;
		$caption = $request->input->data->caption;

		// load from cache file if exists
		$articles = false;
		$fullPath = Utils::getTempDir() . date("Ymd") . md5($category) . '_ddc_category.tmp';
		if(file_exists($fullPath)) $articles = @unserialize(file_get_contents($fullPath));

		// if cache do not exist, load from DDC
		if(!is_array($articles)) {
			// Setup crawler
			$client = new Client();
			$crawler = $client->request('GET', "http://www.diariodecuba.com/etiquetas/$category.html");

			// Collect articles by category
			$articles = [];
			$crawler->filter('.views-row')->each(function($item, $i) use (&$articles){
				try {
					$link = $item->filter('.views-field-title span > a')->attr("href");
					$title = str_replace("'", "", $item->filter('.views-field-title span > a')->text());
					$description = $item->filter('.views-field-field-summary-value p')->text();
					$description = php::truncate($description, 160);
				} catch(Exception $e) {
					return;
				}

				// add to the list of articles
				$articles[] = [
					"title" => $title,
					"link" => $link,
					"description" => $description
				];
			});

			// save to cache
			setlocale(LC_ALL, 'es_ES.UTF-8');
			file_put_contents($fullPath, serialize($articles));
		}

		// in case no results were found
		if(empty($articles)) {
			return $this->error($response, "No hay resultados", "Es extraño, pero no hemos encontrado resultados para esta categoría. Estamos revisando a ver que ocurre.");
		}

		// send data to the view
		$response->setCache(240);
		$response->setLayout('diariodecuba.ejs');
		$response->setTemplate("search.ejs", ["articles"=>$articles, "caption"=>$caption], [Utils::getPathToService("diariodecuba")."/images/diariodecuba-logo.png"]);
	}

	/**
	 * Return an error message
	 *
	 * @author salvipascual
	 * @param Response $response
	 * @param String $title 
	 * @param String $desc 
	 * @return Response
	 */
	private function error(Response $response, $title, $desc)
	{
		// display show error in the log
		error_log("[DIARIODECUBA] $title | $desc");

		// return error template
		$response->setLayout('diariodecuba.ejs');
		return $response->setTemplate('message.ejs', ["header" => $title, "text" => $desc]);
	}
}