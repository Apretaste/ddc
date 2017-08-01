<table width="100%">
	<tr>
		<td align="left"><h1>Noticias de hoy</h1></td>
		<td align="right" valign="top">
			{button href="DIARIODECUBA BUSCAR" popup="true" size="small" body="Complete el asunto agregando una palabra o frase a buscar a continuacion del texto DIARIODECUBA BUSCAR y envie este email" caption="&#10004; Buscar"}
		</td>
	</tr>
</table>

{foreach from=$articles item=article name=arts}
	<b>{link href="DIARIODECUBA HISTORIA {$article['link']}" caption="{$article['title']}"}</b><br/>
	{space5}
	{$article['description']|truncate:200:" ..."}<br/>
	<small>
		<font color="gray">{$article['author']}, {$article['pubDate']|date_format}</font>
		<br/>
		Categor&iacute;as:
		{foreach from=$article['category'] item=category name=cats}
			{link href="DIARIODECUBA CATEGORIA {$category}" caption="{$category}"}
			{if not $smarty.foreach.cats.last}{separator}{/if}
		{/foreach}
	</small>
	{space15}
{/foreach}
