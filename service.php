<?php
	use Goutte\Client;

	class Diariodecuba extends Service
	{
		/**
		 * Function executed when the service is called
		 *
		 * @param Request
		 * @return Response
		 * */
		public function _main(Request $request)
		{
			$response = new Response();
			$response->setResponseSubject("Noticias de hoy");
			$response->createFromTemplate("allStories.tpl", $this->allStories());
			return $response;
		}

		/**
		 * Call to show the news
		 *
		 * @param Request
		 * @return Response
		 * */
		public function _buscar(Request $request)
		{
			// no allow blank entries
			if (empty($request->query))
			{
				$response = new Response();
				$response->setResponseSubject("Busqueda en blanco");
				$response->createFromText("Su busqueda parece estar en blanco, debe decirnos sobre que tema desea leer");
				return $response;
			}

			// search by the query
			try {
				$articles = $this->searchArticles($request->query);
			} catch (Exception $e) {
				return $this->respondWithError();
			}

			// error if the searche return empty
			if(empty($articles))
			{
				$response = new Response();
				$response->setResponseSubject("Su busqueda no genero resultados");
				$response->createFromText("Su busqueda <b>{$request->query}</b> no gener&oacute; ning&uacute;n resultado. Por favor cambie los t&eacute;rminos de b&uacute;squeda e intente nuevamente.");
				return $response;
			}

			$responseContent = array(
				"articles" => $articles,
				"search" => $request->query
			);

			$response = new Response();
			$response->setResponseSubject("Buscar: " . $request->query);
			$response->createFromTemplate("searchArticles.tpl", $responseContent);
			return $response;
		}

		/**
		 * Call to show the news
		 *
		 * @param Request
		 * @return Response
		 * */
		public function _historia(Request $request)
		{
			// no allow blank entries
			if (empty($request->query))
			{
				$response = new Response();
				$response->setResponseSubject("Busqueda en blanco");
				$response->createFromText("Su busqueda parece estar en blanco, debe decirnos que articulo quiere leer");
				return $response;
			}

			// send the actual response
			try {
				$responseContent = $this->story($request->query);
			} catch (Exception $e) {
				return $this->respondWithError();
			}

			// get the image if exist
			$images = array();
			if( ! empty($responseContent['img']))
			{
				$images = array($responseContent['img']);
			}

			$subject = "La historia que usted pidio";

			$response = new Response();
			$response->setResponseSubject($subject);
			$response->createFromTemplate("story.tpl", $responseContent, $images);
			return $response;
		}

		/**
		 * Call list by categoria
		 *
		 * @param Request
		 * @return Response
		 * */
		public function _categoria(Request $request)
		{
			if (empty($request->query))
			{
				$response = new Response();
				$response->setResponseSubject("Categoria en blanco");
				$response->createFromText("Su busqueda parece estar en blanco, debe decirnos sobre que categor&iacute;a desea leer");
				return $response;
			}

			$responseContent = array(
				"articles" => $this->listArticles($request->query)["articles"],
				"category" => $request->query
			);

			$response = new Response();
			$response->setResponseSubject("Categoria: ".$request->query);
			$response->createFromTemplate("catArticles.tpl", $responseContent);
			return $response;
		}

		/**
		 * Search stories
		 *
		 * @param String
		 * @return Array
		 * */
		private function searchArticles($query)
		{
			// Setup crawler
			$client = new Client();
			$url = "http://www.diariodecuba.com/search/node/".urlencode($query);
			$crawler = $client->request('GET', $url);

			// Collect saearch by term
			$articles = array();

			$crawler->filter('dl.search-results.apachesolr_search-results dt.title')->each(function($item, $i) use (&$articles)
			{
				// get title and link from dl dt
				$title = $item->filter('a')->text();
				$link = $item->filter('a')->attr("href");

				// store data collected
				$articles[] = array(
					"pubDate" => null,
					"description" => null,
					"title"	=> $title,
					"link" => $link
				);
			});

			//add remaining data
			$i = 0;
			$crawler->filter('dl.search-results.apachesolr_search-results dd')->each(function($item, $i) use (&$articles)
			{
				// get data from dl dd
				$description = $item->filter('p.search-snippet')->text();
				preg_match("/\d{1,2}\/\d{1,2}\/\d{4}/", $item->filter('p.search-info')->text(), $matches); //extract the date from info field
				$date = DateTime::createFromFormat('d/m/Y', $matches[0]);
				// store list of articles
				$articles[$i]["description"] = $description;
				$articles[$i++]["pubDate"] = $date;
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
			$articles = array();
			$crawler->filter('channel item')->each(function($item, $i) use (&$articles, $query)
			{
				// if category matches, add to list of articles
				$item->filter('category')->each(function($cat, $i) use (&$articles, $query, $item)
				{
					if (strtoupper($cat->text()) == strtoupper($query))
					{
						$title = $item->filter('title')->text();
						$link = $this->urlSplit($item->filter('link')->text());
						$pubDate = $item->filter('pubDate')->text();
						$description = $item->filter('description')->text();
						$cadenaAborrar = "/<!-- google_ad_section_start --><!-- google_ad_section_end --><p>/";
						$description = preg_replace($cadenaAborrar, '', $description);
						$description = preg_replace("/<\/?a[^>]*>/", '', $description);//quitamos las <a></a>
						$description = preg_replace("/<\/?p[^>]*>/", '', $description);//quitamos las <p></p>
						
						$author = "desconocido";
						if ($item->filter('dc|creator')->count() > 0)
						{
							$authorString = trim($item->filter('dc|creator')->text());
							$author = "({$authorString})";
						}

						$articles[] = array(
							"title" => $title,
							"link" => $link,
							"pubDate" => $pubDate,
							"description" => $description,
							"author" => $author
						);
					}
				});
			});

			// Return response content
			return array("articles" => $articles);
		}

		/**
		 * Get all stories from a query
		 *
		 * @return Array
		 */
		private function allStories()
		{
			// create a new client
			$client = new Client();
			$guzzle = $client->getClient();
			$guzzle->setDefaultOption('verify', false);
			$client->setClient($guzzle);

			// create a crawler
			$crawler = $client->request('GET', "http://www.diariodecuba.com/rss.xml"); //http://www.martinoticias.com/api/epiqq

			$articles = array();
			$crawler->filter('channel item')->each(function($item, $i) use (&$articles)
			{
				
				// get all parameters
				$title = $item->filter('title')->text();
				$link = $this->urlSplit($item->filter('link')->text());
				$description = $item->filter('description')->text();
				$cadenaAborrar = "/<!-- google_ad_section_start --><!-- google_ad_section_end --><p>/";
				$description = preg_replace($cadenaAborrar, '', $description);
				$description = preg_replace("/<\/?a[^>]*>/", '', $description);//quitamos las <a></a>
				$description = preg_replace("/<\/?p[^>]*>/", '', $description);//quitamos las <p></p>
				$pubDate = $item->filter('pubDate')->text();
				$category = $item->filter('category')->each(function($category, $j) {return $category->text();});

				if ($item->filter('dc|creator')->count() == 0) $author = "desconocido";
				else
				{
					$authorString = trim($item->filter('dc|creator')->text());
					$author = "({$authorString})";
				}

				$categoryLink = array();
				foreach ($category as $currCategory)
				{
					$categoryLink[] = $currCategory;
				}

				$articles[] = array(
					"title" => $title,
					"link" => $link,
					"pubDate" => $pubDate,
					"description" => $description,
					"category" => $category,
					"categoryLink" => $categoryLink,
					"author" => $author
				);
			});

			// return response content
			return array("articles" => $articles);
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
			$guzzle->setDefaultOption('verify', false);
			$client->setClient($guzzle);

			// create a crawler
			$crawler = $client->request('GET', "http://www.diariodecuba.com/$query");

			// search for title
			$title = $crawler->filter('h1.title')->text();

			// get the intro

			$titleObj = $crawler->filter('div.content:nth-child(1) p:nth-child(1)');
			$intro = $titleObj->count()>0 ? $titleObj->text() : "";

			// get the images
			$imageObj = $crawler->filter('div.captionimage img');
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
					$this->utils->optimizeImage($img, 300);
				}
			}

			// get the array of paragraphs of the body
			$paragraphs = $crawler->filter('div.content:nth-child(2) > p');
			$content = array();
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
			error_log("WARNING: ERROR ON SERVICE MARTI");

			$response = new Response();
			$response->setResponseSubject("Error en peticion");
			$response->createFromText("Lo siento pero hemos tenido un error inesperado. Enviamos una peticion para corregirlo. Por favor intente nuevamente mas tarde.");
			return $response;
		}
	}
?>
