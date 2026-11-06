<div class="gridfield-action-menu" data-gridfield-menu data-error="$Error.ATT">
    <button type="button" name="{$MenuID.ATT}_toggle" class="btn btn-secondary dropdown-toggle no-change-track" data-menu-toggle
        aria-haspopup="menu" aria-expanded="false" aria-controls="$MenuID.ATT" hidden><% if $Icon %><span class="font-icon-$Icon.ATT" aria-hidden="true"></span> <% end_if %>$Title.XML</button>
    <div id="$MenuID.ATT" class="gridfield-pro-menu" role="menu" aria-label="$Title.ATT">
        <% loop $Items %>
            <% if $Action %>
                $Action.Field
            <% else_if $Disabled %>
                <span class="dropdown-item" role="menuitem" aria-disabled="true" data-menu-item>$Label.XML</span>
            <% else %>
                <a class="dropdown-item <% if $Download %>gridfield-action-menu__download<% else %>action action-detail<% end_if %>"
                    href="$URL.ATT" role="menuitem" data-menu-item><% if $Icon %><span class="font-icon-$Icon.ATT" aria-hidden="true"></span> <% end_if %>$Label.XML</a>
            <% end_if %>
        <% end_loop %>
    </div>
    <span class="gridfield-action-menu__error" role="alert" hidden></span>
</div>
