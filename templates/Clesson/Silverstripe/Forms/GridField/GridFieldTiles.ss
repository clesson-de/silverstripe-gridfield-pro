<% require css('clesson-de/silverstripe-gridfield-pro: client/dist/css/silverstripe-gridfield-pro.css') %>
<% require javascript('clesson-de/silverstripe-gridfield-pro: client/dist/js/silverstripe-gridfield-pro.js') %>

<div class="gridfield-tiles" style="--tile-height:{$TileHeight}px;--tile-width:{$TileWidth}px;--tile-gap:{$TileGap}px">
    <% loop $Items %>
        <div class="gridfield-tiles--item"<% if $Link %> data-href="{$Link}"<% end_if %>>
            {$Content}
        </div>
    <% end_loop %>
</div>



