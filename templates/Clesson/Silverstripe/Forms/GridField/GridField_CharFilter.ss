<% require css('clesson-de/silverstripe-gridfield-pro: client/dist/css/silverstripe-gridfield-pro.css') %>
<div class="gridfield-charfilter">
    <div class="gridfield-charfilter--chars">
    <% loop $Fields %>  {$Field}
    <% end_loop %>
    </div>
</div>
