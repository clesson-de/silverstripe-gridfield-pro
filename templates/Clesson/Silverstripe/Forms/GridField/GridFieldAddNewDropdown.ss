<div class="gridfield-add-new-dropdown">
    <% if $Multiple %>
        <button type="button" class="btn btn-primary dropdown-toggle gridfield-add-new-dropdown__toggle no-change-track"
            aria-haspopup="menu" aria-expanded="false" aria-controls="$MenuID.ATT">$Title.XML</button>
        <div id="$MenuID.ATT" class="dropdown-menu gridfield-add-new-dropdown__menu" role="menu" hidden>
            <% loop $Choices %>
                <a class="dropdown-item action action-detail new-link gridfield-add-new-dropdown__link" role="menuitem" tabindex="-1"
                    href="$Link.ATT">$Label.XML</a>
            <% end_loop %>
        </div>
    <% else %>
        <% loop $Choices %>
            <a class="action action-detail new-link btn btn-primary gridfield-add-new-dropdown__direct" href="$Link.ATT">$Up.Title.XML</a>
        <% end_loop %>
    <% end_if %>
</div>
