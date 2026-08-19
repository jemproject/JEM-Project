<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/featurepolicy.class.php';

final class FeaturePolicyTest extends TestCase
{
    public function testOnlyCompletedProfilesAreSelectable(): void
    {
        self::assertTrue(JemFeaturePolicy::isSelectable(JemFeaturePolicy::PROFILE_ESSENTIAL));
        self::assertTrue(JemFeaturePolicy::isSelectable(JemFeaturePolicy::PROFILE_ADVANCED));
        self::assertFalse(JemFeaturePolicy::isSelectable(JemFeaturePolicy::PROFILE_COMMERCE));
    }

    public function testEssentialKeepsAdvancedAndCommerceCapabilitiesDisabled(): void
    {
        $policy = new JemFeaturePolicy(JemFeaturePolicy::PROFILE_ESSENTIAL);

        self::assertTrue($policy->isEssential());
        self::assertFalse($policy->allows(JemFeaturePolicy::FEATURE_PROGRAMMES));
        self::assertFalse($policy->allows(JemFeaturePolicy::FEATURE_VENUE_CAPACITY));
        self::assertFalse($policy->allows(JemFeaturePolicy::FEATURE_PRICING));
        self::assertFalse($policy->allows(JemFeaturePolicy::FEATURE_TICKETING));
    }

    public function testAdvancedEnablesNonCommercialCapabilitiesOnly(): void
    {
        $policy = new JemFeaturePolicy(JemFeaturePolicy::PROFILE_ADVANCED);

        self::assertTrue($policy->isAdvanced());
        self::assertTrue($policy->allows(JemFeaturePolicy::FEATURE_PROGRAMMES));
        self::assertTrue($policy->allows(JemFeaturePolicy::FEATURE_VENUE_HIERARCHY));
        self::assertTrue($policy->allows(JemFeaturePolicy::FEATURE_VENUE_CAPACITY));
        self::assertTrue($policy->allows(JemFeaturePolicy::FEATURE_SPACE_SCHEDULING));
        self::assertTrue($policy->allows(JemFeaturePolicy::FEATURE_CAPACITY_REGISTRATION));
        self::assertTrue($policy->allows(JemFeaturePolicy::FEATURE_NOTIFICATION_AUTOMATION));
        self::assertFalse($policy->allows(JemFeaturePolicy::FEATURE_PRICING));
        self::assertFalse($policy->allows(JemFeaturePolicy::FEATURE_PAYMENTS));
        self::assertFalse($policy->allows(JemFeaturePolicy::FEATURE_TICKETING));
    }

    public function testCommerceCannotBeActivatedBySubmittedConfiguration(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('COM_JEM_OPERATING_PROFILE_NOT_AVAILABLE');

        JemFeaturePolicy::normaliseSelectableProfile(JemFeaturePolicy::PROFILE_COMMERCE);
    }

    public function testUnknownStoredProfileFallsBackToEssential(): void
    {
        $policy = new JemFeaturePolicy('tampered-profile');

        self::assertSame(JemFeaturePolicy::PROFILE_ESSENTIAL, $policy->getProfile());
        self::assertFalse($policy->allows(JemFeaturePolicy::FEATURE_PROGRAMMES));
    }

    public function testStoredCommerceProfileIsFailClosed(): void
    {
        $policy = new JemFeaturePolicy(JemFeaturePolicy::PROFILE_COMMERCE);

        self::assertSame(JemFeaturePolicy::PROFILE_COMMERCE, $policy->getProfile());
        self::assertFalse($policy->allows(JemFeaturePolicy::FEATURE_PRICING));
        self::assertFalse($policy->allows(JemFeaturePolicy::FEATURE_TICKETING));
    }
}
