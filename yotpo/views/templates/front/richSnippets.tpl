<span class="aggregateRating no-display" itemprop="aggregateRating" itemscope itemtype="http://schema.org/AggregateRating">
{if $yotpoRatingValue == '0'}
	<span class="no-display" itemprop="worstRating">1</span>
	<span class="no-display" itemprop="bestRating">5</span>
{/if}
	<span itemprop="ratingValue">{$yotpoRatingValue}</span>
	<span itemprop="reviewCount">{$yotpoReviewCount}</span>
</span>