<div class="gridfield-search" data-state-key="$StateKey.ATT" data-paginator="$HasPaginator.ATT">
    <label class="sr-only" for="$InputID.ATT">$Placeholder.XML</label>
    <div class="gridfield-search__control">
        <input id="$InputID.ATT" name="$InputName.ATT" class="gridfield-search__input no-change-track"
            type="text" value="$Query.ATT" placeholder="$Placeholder.ATT" maxlength="$MaxLength.ATT" autocomplete="off">
        <span class="gridfield-search__icon font-icon-search" aria-hidden="true"<% if $Query %> hidden<% end_if %>></span>
        <button type="button" class="gridfield-search__clear" aria-label="$Clear.ATT"<% if not $Query %> hidden<% end_if %>><span class="font-icon-cancel" aria-hidden="true"></span></button>
    </div>
</div>
