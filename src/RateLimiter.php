<?php

namespace Spatie\GuzzleRateLimit;

class RateLimiter
{
    const TIME_FRAME_MINUTE = 'minute';
    const TIME_FRAME_SECOND = 'second';

    /** @var int */
    protected $limit;

    /** @var string */
    protected $timeFrame;

    /** @var \Spatie\RateLimiter\Store */
    protected $store;

    /** @var \Spatie\GuzzleRateLimiter\TimeMachine */
    protected $timeMachine;

    public function __construct(
        int $limit,
        string $timeFrame,
        Store $store,
        TimeMachine $timeMachine
    ) {
        $this->limit = $limit;
        $this->timeFrame = $timeFrame;
        $this->store = $store;
        $this->timeMachine = $timeMachine;
    }

    public function handle(callable $callback)
    {
        $delayUntilNextRequest = $this->delayUntilNextRequest();

        if ($delayUntilNextRequest > 0) {
            $this->timeMachine->sleep($delayUntilNextRequest);
        }

        $this->store->push(
            $this->timeMachine->getCurrentTime(),
            $this->limit
        );

        $callback();
    }

    protected function delayUntilNextRequest(): int
    {
        $currentTimeFrameStart = $this->timeMachine->getCurrentTime() - $this->timeFrameLengthInMilliseconds();

        $requestsInCurrentTimeFrame = array_values(array_filter(
            $this->store->get(),
            function (int $timestamp) use ($currentTimeFrameStart) {
                return $timestamp >= $currentTimeFrameStart;
            }
        ));

        if (count($requestsInCurrentTimeFrame) < $this->limit) {
            return 0;
        }

        $oldestRequestStartTimeRelativeToCurrentTimeFrame =
            $this->timeMachine->getCurrentTime() - $requestsInCurrentTimeFrame[0];

        return $this->timeFrameLengthInMilliseconds() - $oldestRequestStartTimeRelativeToCurrentTimeFrame;
    }

    protected function timeFrameLengthInMilliseconds(): int
    {
        if ($this->timeFrame === self::TIME_FRAME_MINUTE) {
            return 60 * 1000;
        }

        return 1000;
    }
}
