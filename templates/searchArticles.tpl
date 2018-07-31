<h1>Buscar: {$search|ucfirst}</h1>

{foreach from=$articles item=article name=arts}
	<b>{link href="DIARIODECUBA HISTORIA {$article['link']}" caption="{$article['title']}"}</b><br/>
	{$article['description']}<br/><!--|truncate:200:" ..." esto diario de cuba ya lo hace por nosotros-->
	{space15}
{/foreach}

{space5}

<center>
	{button href="DIARIODECUBA" caption="Titulares"}
</center>