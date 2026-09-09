import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { useRouter } from 'expo-router';
import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import type { LayoutChangeEvent } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import {
  FontSize,
  FontWeight,
  Gradients,
  Layout,
  LineHeight,
  Palette,
  Radius,
  Shadow,
  Spacing,
  Typography,
} from '@/constants/theme';
import { showRewarded } from '@/lib/ads';
import { formatCoins } from '@/lib/format';
import {
  AD_MAX_COINS,
  AD_TASKS,
  AD_TASK_LIMIT,
  CHECKIN_COINS,
  READING_MILESTONES,
  RewardsProvider,
  WEEKDAY_LABELS,
  useRewards,
} from '@/store/rewards';
import { useWallet } from '@/store/wallet';

/**
 * TRANG PHẦN THƯỞNG.
 *
 * Provider được bọc NGAY TRONG màn này (không đụng `_layout.tsx`), nên toàn bộ
 * số liệu nhiệm vụ chỉ sống cùng tab "Phần thưởng"; xu vẫn đi qua ví chung.
 */

/* ------------------------------------------------------------------ */
/* THANH MỐC DÙNG CHUNG                                                */
/* ------------------------------------------------------------------ */

/** bề rộng 1 ô mốc (vòng tròn + nhãn dưới) */
const CELL_WIDTH = 56;
/** đường kính vòng tròn mốc */
const NODE_SIZE = 38;
/** độ dày đường ray */
const RAIL_HEIGHT = 6;

type TrackTone = 'accent' | 'coin';
type NodeState = 'done' | 'ready' | 'todo';

/** Màu vàng xu cho thanh tiến trình — tuple readonly cho LinearGradient SDK 57. */
const COIN_FILL = [Palette.coinSoft, Palette.coin] as const;

const TRACK_TONES = {
  accent: {
    solid: Palette.accentDeep,
    on: Palette.onAccent,
    deep: Palette.accentDeep,
    fill: Gradients.accent,
  },
  coin: {
    solid: Palette.coin,
    on: Palette.onLight,
    deep: Palette.coinDeep,
    fill: COIN_FILL,
  },
} as const;

interface TrackItem {
  key: string;
  /** chữ trong vòng tròn khi chưa hoàn thành, vd '+15' */
  value: string;
  /** nhãn dưới vòng tròn, vd '10 phút' */
  label: string;
  state: NodeState;
  onPress?: () => void;
}

interface MilestoneTrackProps {
  items: TrackItem[];
  /** vị trí tiến trình tính theo CHỈ SỐ MỐC (0 = mốc đầu, n-1 = mốc cuối) */
  position: number;
  tone: TrackTone;
}

/**
 * Thanh ngang nối các mốc: đường ray xám + phần đã đạt tô gradient + vòng tròn.
 * Toạ độ tâm mốc tính từ bề rộng đo được, nên co giãn theo mọi khổ máy.
 */
function MilestoneTrack({ items, position, tone }: MilestoneTrackProps) {
  const [width, setWidth] = useState(0);
  const colors = TRACK_TONES[tone];

  const onLayout = useCallback((e: LayoutChangeEvent) => {
    setWidth(e.nativeEvent.layout.width);
  }, []);

  /** bề rộng phần ray đã tô, tính từ tâm mốc đầu tiên */
  const fillWidth = useMemo(() => {
    if (width <= 0 || items.length < 2) return 0;
    const step = (width - CELL_WIDTH) / (items.length - 1);
    const clamped = Math.max(0, Math.min(items.length - 1, position));
    return clamped * step;
  }, [width, items.length, position]);

  return (
    <View style={styles.track} onLayout={onLayout}>
      <View style={styles.rail} />
      {fillWidth > 0 ? (
        <LinearGradient
          colors={colors.fill}
          start={{ x: 0, y: 0 }}
          end={{ x: 1, y: 0 }}
          style={[styles.railFill, { width: fillWidth }]}
        />
      ) : null}

      <View style={styles.trackCells}>
        {items.map((item) => {
          const done = item.state === 'done';
          const ready = item.state === 'ready';
          const nodeStyle = done
            ? { backgroundColor: colors.solid, borderColor: colors.solid, borderWidth: 2 }
            : ready
              ? // nền ĐẶC (không phải màu mờ) để đường ray không lộ qua con số
                { backgroundColor: Palette.surface, borderColor: colors.deep, borderWidth: 2 }
              : {
                  backgroundColor: Palette.surfaceAlt,
                  borderColor: Palette.border,
                  borderWidth: StyleSheet.hairlineWidth,
                };

          const body = (
            <>
              <View style={[styles.node, nodeStyle]}>
                {done ? (
                  <Ionicons name="checkmark" size={18} color={colors.on} />
                ) : (
                  <Text
                    style={[styles.nodeValue, { color: ready ? colors.deep : Palette.faint }]}
                    numberOfLines={1}>
                    {item.value}
                  </Text>
                )}
              </View>
              <Text
                style={[
                  styles.nodeLabel,
                  (done || ready) && styles.nodeLabelActive,
                ]}
                numberOfLines={1}>
                {item.label}
              </Text>
            </>
          );

          if (item.onPress) {
            return (
              <Pressable
                key={item.key}
                onPress={item.onPress}
                accessibilityRole="button"
                accessibilityLabel={`Claim ${item.label} reward`}
                style={({ pressed }) => [styles.trackCell, pressed && styles.pressed]}>
                {body}
              </Pressable>
            );
          }

          return (
            <View key={item.key} style={styles.trackCell}>
              {body}
            </View>
          );
        })}
      </View>
    </View>
  );
}

/* ------------------------------------------------------------------ */
/* MÀN HÌNH                                                            */
/* ------------------------------------------------------------------ */

export default function RewardsScreen() {
  return (
    <RewardsProvider>
      <RewardsContent />
    </RewardsProvider>
  );
}

/** Vị trí tiến trình của thanh mốc thời gian đọc, theo chỉ số mốc. */
function readingPosition(minutes: number): number {
  const list = READING_MILESTONES;
  for (let i = 0; i < list.length - 1; i += 1) {
    const from = list[i].minutes;
    const to = list[i + 1].minutes;
    if (minutes < from) return 0;
    if (minutes < to) return i + (minutes - from) / (to - from);
  }
  return list.length - 1;
}

function RewardsContent() {
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { coins } = useWallet();
  const {
    ready,
    today,
    weekDays,
    checkedDays,
    checkedToday,
    todayCheckInCoins,
    checkIn,
    readingMinutes,
    readingClaimed,
    claimableReading,
    nextReading,
    claimReading,
    adsWatched,
    adsCoins,
    nextAdCoins,
    recordAdWatch,
  } = useRewards();

  const [message, setMessage] = useState<{ text: string; ok: boolean; at: number } | null>(null);
  const [watchingAd, setWatchingAd] = useState(false);

  /** Hiện thông báo ngắn rồi tự ẩn. */
  const notify = useCallback((text: string, ok: boolean) => {
    setMessage({ text, ok, at: Date.now() });
  }, []);

  useEffect(() => {
    if (!message) return;
    const timer = setTimeout(() => setMessage(null), 3000);
    return () => clearTimeout(timer);
  }, [message]);

  // --- Mốc thời gian đọc ---
  const readingItems = useMemo<TrackItem[]>(
    () =>
      READING_MILESTONES.map((m) => {
        const claimed = readingClaimed.includes(m.minutes);
        const reached = readingMinutes >= m.minutes;
        const state: NodeState = claimed ? 'done' : reached ? 'ready' : 'todo';
        return {
          key: String(m.minutes),
          value: `+${m.coins}`,
          label: `${m.minutes} min`,
          state,
          onPress:
            state === 'ready'
              ? () => {
                  const got = claimReading(m.minutes);
                  if (got > 0) notify(`Nice! You earned +${formatCoins(got)}.`, true);
                }
              : undefined,
        };
      }),
    [readingClaimed, readingMinutes, claimReading, notify],
  );

  const onPrimaryPress = useCallback(() => {
    if (claimableReading) {
      const got = claimReading(claimableReading.minutes);
      if (got > 0) notify(`Nice! You earned +${formatCoins(got)}.`, true);
      return;
    }
    router.navigate('/');
  }, [claimableReading, claimReading, notify, router]);

  // --- Điểm danh ---
  const onCheckIn = useCallback(() => {
    const got = checkIn();
    if (got > 0) {
      notify(`Checked in, +${formatCoins(got)}!`, true);
    } else {
      notify("You've already checked in today. Come back tomorrow!", false);
    }
  }, [checkIn, notify]);

  // --- Nhiệm vụ quảng cáo ---
  const adItems = useMemo<TrackItem[]>(
    () =>
      AD_TASKS.map((coinValue, i) => ({
        key: `ad-${i}`,
        value: `+${coinValue}`,
        label: `Task ${i + 1}`,
        state: i < adsWatched ? 'done' : i === adsWatched ? 'ready' : 'todo',
      })),
    [adsWatched],
  );

  const adsDone = adsWatched >= AD_TASK_LIMIT;

  const onWatchAd = useCallback(async () => {
    if (watchingAd || adsDone) return;
    setWatchingAd(true);
    try {
      const { rewarded } = await showRewarded();
      if (!rewarded) {
        notify("You didn't finish the ad, so no coins were added.", false);
        return;
      }
      const got = recordAdWatch();
      if (got > 0) {
        notify(`Got +${formatCoins(got)} from the ad!`, true);
      } else {
        notify("You've earned all ad coins for today.", false);
      }
    } catch {
      notify('Could not load the ad, please try again later.', false);
    } finally {
      setWatchingAd(false);
    }
  }, [watchingAd, adsDone, recordAdWatch, notify]);

  // --- Chữ phụ dưới nút lớn ---
  const readHint = nextReading
    ? `Read ${Math.max(1, nextReading.minutes - readingMinutes)} more min to get +${formatCoins(nextReading.coins)}`
    : "You've completed every reading milestone today";

  return (
    <ScrollView
      style={styles.screen}
      contentContainerStyle={[
        styles.content,
        { paddingTop: insets.top + Spacing.sm, paddingBottom: Spacing.huge },
      ]}
      showsVerticalScrollIndicator={false}>
      {/* --- Đầu trang: tiêu đề + ví xu --- */}
      <View style={styles.header}>
        <View style={styles.headerText}>
          <Text style={styles.heading}>Reading Rewards</Text>
          <View style={styles.refreshRow}>
            <Ionicons name="refresh-outline" size={12} color={Palette.faint} />
            <Text style={styles.subheading}>Resets daily at 00:00</Text>
          </View>
        </View>

        <View style={styles.wallet}>
          <View style={styles.walletCoin}>
            <Ionicons name="logo-usd" size={12} color={Palette.white} />
          </View>
          <Text style={styles.walletValue}>{coins}</Text>
          <Text style={styles.walletUnit}>{coins === 1 ? 'coin' : 'coins'}</Text>
        </View>
      </View>

      {message ? (
        <View style={[styles.toast, message.ok ? styles.toastOk : styles.toastWarn]}>
          <Ionicons
            name={message.ok ? 'checkmark-circle' : 'information-circle'}
            size={16}
            color={message.ok ? Palette.freeDeep : Palette.coinDeep}
          />
          <Text
            style={[
              styles.toastText,
              { color: message.ok ? Palette.freeDeep : Palette.coinDeep },
            ]}>
            {message.text}
          </Text>
        </View>
      ) : null}

      {/* --- Mốc thời gian đọc --- */}
      <View style={styles.card}>
        <View style={styles.cardHead}>
          <View style={[styles.cardIcon, { backgroundColor: Palette.accentDim }]}>
            <Ionicons name="time" size={17} color={Palette.accentDeep} />
          </View>
          <View style={styles.cardHeadText}>
            <Text style={styles.cardTitle}>Reading milestones</Text>
            <Text style={styles.cardSub}>Earn coins as you read</Text>
          </View>
          <View style={styles.cardBadge}>
            <Text style={styles.cardBadgeText}>{readingMinutes} min</Text>
          </View>
        </View>

        <MilestoneTrack
          items={readingItems}
          position={readingPosition(readingMinutes)}
          tone="accent"
        />

        <Pressable
          onPress={onPrimaryPress}
          disabled={!ready}
          accessibilityRole="button"
          style={({ pressed }) => [
            styles.primaryWrap,
            !ready && styles.disabled,
            pressed && styles.pressed,
          ]}>
          <LinearGradient
            colors={Gradients.accent}
            start={{ x: 0, y: 0 }}
            end={{ x: 1, y: 1 }}
            style={styles.primary}>
            <Ionicons
              name={claimableReading ? 'gift' : 'book'}
              size={18}
              color={Palette.onAccent}
            />
            <Text style={styles.primaryText}>
              {claimableReading ? `Get +${formatCoins(claimableReading.coins)}` : 'Read now'}
            </Text>
          </LinearGradient>
        </Pressable>

        <Text style={styles.primaryHint}>
          You&apos;ve read <Text style={styles.primaryHintStrong}>{readingMinutes} min</Text> today ·{' '}
          {readHint}
        </Text>
      </View>

      {/* --- Hai ô nhỏ: điểm danh + vòng quay --- */}
      <View style={styles.tileRow}>
        <Pressable
          onPress={onCheckIn}
          disabled={!ready || checkedToday}
          accessibilityRole="button"
          style={({ pressed }) => [styles.tile, pressed && styles.pressed]}>
          <View
            style={[
              styles.tileIcon,
              { backgroundColor: checkedToday ? Palette.freeDim : Palette.accentDim },
            ]}>
            <Ionicons
              name={checkedToday ? 'checkmark-done' : 'calendar'}
              size={19}
              color={checkedToday ? Palette.freeDeep : Palette.accentDeep}
            />
          </View>
          <Text style={styles.tileTitle}>Check in</Text>
          <Text
            style={[
              styles.tileStatus,
              { color: checkedToday ? Palette.freeDeep : Palette.accentDeep },
            ]}
            numberOfLines={1}>
            {checkedToday ? 'Checked in' : `Get +${formatCoins(todayCheckInCoins)}`}
          </Text>
        </Pressable>

        <View style={[styles.tile, styles.tileSoon]}>
          <View style={[styles.tileIcon, { backgroundColor: Palette.surfaceAlt }]}>
            <Ionicons name="disc" size={19} color={Palette.faint} />
          </View>
          <Text style={styles.tileTitle}>Lucky draw</Text>
          <Text style={[styles.tileStatus, { color: Palette.faint }]} numberOfLines={1}>
            Coming soon
          </Text>
        </View>
      </View>

      {/* --- Nhiệm vụ quảng cáo --- */}
      <View style={styles.card}>
        <View style={styles.cardHead}>
          <View style={[styles.cardIcon, { backgroundColor: Palette.coinDim }]}>
            <Ionicons name="videocam" size={17} color={Palette.coinDeep} />
          </View>
          <View style={styles.cardHeadText}>
            <Text style={styles.cardTitle}>Daily ad tasks</Text>
            <Text style={styles.cardSub}>
              Earned <Text style={styles.cardSubStrong}>{adsCoins}</Text> / {AD_MAX_COINS} coins
            </Text>
          </View>
        </View>

        <MilestoneTrack
          items={adItems}
          position={adsWatched > 0 ? adsWatched - 1 : 0}
          tone="coin"
        />

        <Pressable
          onPress={onWatchAd}
          disabled={!ready || adsDone || watchingAd}
          accessibilityRole="button"
          style={({ pressed }) => [
            styles.adButton,
            (adsDone || !ready) && styles.adButtonOff,
            pressed && styles.pressed,
          ]}>
          {watchingAd ? (
            <ActivityIndicator size="small" color={Palette.coinDeep} />
          ) : (
            <Ionicons
              name={adsDone ? 'checkmark-circle' : 'play-circle'}
              size={19}
              color={adsDone ? Palette.freeDeep : Palette.coinDeep}
            />
          )}
          <Text
            style={[styles.adButtonText, adsDone && { color: Palette.freeDeep }]}
            numberOfLines={1}>
            {watchingAd
              ? 'Loading ad…'
              : adsDone
                ? 'All done for today'
                : `Watch ad · +${formatCoins(nextAdCoins)}`}
          </Text>
        </Pressable>

        <Text style={styles.adHint}>
          {Math.max(0, AD_TASK_LIMIT - adsWatched)} left today
        </Text>
      </View>

      {/* --- Lịch điểm danh tuần --- */}
      <View style={styles.card}>
        <View style={styles.cardHead}>
          <View style={[styles.cardIcon, { backgroundColor: Palette.accentDim }]}>
            <Ionicons name="calendar-number" size={17} color={Palette.accentDeep} />
          </View>
          <View style={styles.cardHeadText}>
            <Text style={styles.cardTitle}>This week</Text>
            <Text style={styles.cardSub}>Checked in {checkedDays.length}/7 days</Text>
          </View>
        </View>

        <View style={styles.weekRow}>
          {weekDays.map((day, i) => {
            const checked = checkedDays.includes(day);
            const isToday = day === today;
            const weekend = i === 0 || i === 6;
            return (
              <View key={day} style={styles.dayCell}>
                <Text style={[styles.dayLabel, isToday && styles.dayLabelToday]} numberOfLines={1}>
                  {WEEKDAY_LABELS[i]}
                </Text>
                <View
                  style={[
                    styles.dayCircle,
                    weekend && !checked && styles.dayCircleWeekend,
                    checked && styles.dayCircleChecked,
                    isToday && !checked && styles.dayCircleToday,
                  ]}>
                  {checked ? (
                    <Ionicons name="checkmark" size={16} color={Palette.onAccent} />
                  ) : weekend ? (
                    <Ionicons name="gift" size={16} color={Palette.coinDeep} />
                  ) : (
                    <Text
                      style={[styles.dayCoins, isToday && { color: Palette.accentDeep }]}
                      numberOfLines={1}>
                      {CHECKIN_COINS[i]}
                    </Text>
                  )}
                </View>
                <Text
                  style={[styles.dayCoinsLabel, checked && { color: Palette.freeDeep }]}
                  numberOfLines={1}>
                  +{CHECKIN_COINS[i]}
                </Text>
              </View>
            );
          })}
        </View>

        <Text style={styles.weekHint}>
          Check in every day to keep your streak. Saturday and Sunday give bigger rewards.
        </Text>
      </View>
    </ScrollView>
  );
}

/* ------------------------------------------------------------------ */
/* STYLE                                                               */
/* ------------------------------------------------------------------ */

const styles = StyleSheet.create({
  screen: {
    flex: 1,
    backgroundColor: Palette.bg,
  },
  content: {
    paddingHorizontal: Spacing.screen,
    gap: Spacing.md,
  },

  // --- Đầu trang ---
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: Spacing.md,
  },
  headerText: {
    flexShrink: 1,
  },
  heading: {
    ...Typography.h1,
  },
  refreshRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.xs,
    marginTop: Spacing.xxs,
  },
  subheading: {
    color: Palette.faint,
    fontSize: FontSize.caption,
    fontWeight: FontWeight.medium,
    lineHeight: LineHeight.caption,
  },
  wallet: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.xs,
    paddingLeft: Spacing.xs,
    paddingRight: Spacing.md,
    paddingVertical: Spacing.xs,
    borderRadius: Radius.pill,
    backgroundColor: Palette.surface,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: Palette.coinBorder,
    ...Shadow.sm,
  },
  walletCoin: {
    width: 22,
    height: 22,
    borderRadius: Radius.pill,
    backgroundColor: Palette.coin,
    alignItems: 'center',
    justifyContent: 'center',
  },
  walletValue: {
    color: Palette.coinDeep,
    fontSize: FontSize.h2,
    fontWeight: FontWeight.black,
  },
  walletUnit: {
    color: Palette.coinDeep,
    fontSize: FontSize.caption,
    fontWeight: FontWeight.semibold,
  },

  // --- Thông báo ngắn ---
  toast: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.sm,
    paddingHorizontal: Spacing.md,
    paddingVertical: Spacing.sm,
    borderRadius: Radius.md,
    borderWidth: StyleSheet.hairlineWidth,
  },
  toastOk: {
    backgroundColor: Palette.freeDim,
    borderColor: Palette.freeBorder,
  },
  toastWarn: {
    backgroundColor: Palette.coinDim,
    borderColor: Palette.coinBorder,
  },
  toastText: {
    flex: 1,
    fontSize: FontSize.caption,
    fontWeight: FontWeight.semibold,
    lineHeight: LineHeight.caption,
  },

  // --- Card chung ---
  card: {
    backgroundColor: Palette.surface,
    borderRadius: Radius.card,
    padding: Spacing.lg,
    gap: Spacing.lg,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: Palette.border,
    ...Shadow.card,
  },
  cardHead: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.md,
  },
  cardIcon: {
    width: 36,
    height: 36,
    borderRadius: Radius.pill,
    alignItems: 'center',
    justifyContent: 'center',
  },
  cardHeadText: {
    flex: 1,
  },
  cardTitle: {
    color: Palette.text,
    fontSize: FontSize.h2,
    fontWeight: FontWeight.bold,
    lineHeight: LineHeight.h2,
  },
  cardSub: {
    color: Palette.muted,
    fontSize: FontSize.caption,
    fontWeight: FontWeight.medium,
    lineHeight: LineHeight.caption,
    marginTop: 1,
  },
  cardSubStrong: {
    color: Palette.coinDeep,
    fontWeight: FontWeight.bold,
  },
  cardBadge: {
    paddingHorizontal: Spacing.sm + 2,
    paddingVertical: 3,
    borderRadius: Radius.pill,
    backgroundColor: Palette.accentDim,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: Palette.accentBorder,
  },
  cardBadgeText: {
    color: Palette.accentDeep,
    fontSize: FontSize.tiny,
    fontWeight: FontWeight.bold,
  },

  // --- Thanh mốc ---
  track: {
    justifyContent: 'center',
  },
  rail: {
    position: 'absolute',
    left: CELL_WIDTH / 2,
    right: CELL_WIDTH / 2,
    top: (NODE_SIZE - RAIL_HEIGHT) / 2,
    height: RAIL_HEIGHT,
    borderRadius: Radius.pill,
    backgroundColor: Palette.surfaceAlt,
  },
  railFill: {
    position: 'absolute',
    left: CELL_WIDTH / 2,
    top: (NODE_SIZE - RAIL_HEIGHT) / 2,
    height: RAIL_HEIGHT,
    borderRadius: Radius.pill,
  },
  trackCells: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
  },
  trackCell: {
    width: CELL_WIDTH,
    alignItems: 'center',
    gap: Spacing.xs,
  },
  node: {
    width: NODE_SIZE,
    height: NODE_SIZE,
    borderRadius: Radius.pill,
    alignItems: 'center',
    justifyContent: 'center',
  },
  nodeValue: {
    fontSize: FontSize.tiny,
    fontWeight: FontWeight.bold,
  },
  nodeLabel: {
    color: Palette.faint,
    fontSize: FontSize.tiny,
    fontWeight: FontWeight.medium,
  },
  nodeLabelActive: {
    color: Palette.text,
    fontWeight: FontWeight.semibold,
  },

  // --- Nút lớn ---
  primaryWrap: {
    borderRadius: Radius.pill,
    overflow: 'hidden',
    ...Shadow.float,
  },
  primary: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: Spacing.sm,
    height: 50,
    borderRadius: Radius.pill,
  },
  primaryText: {
    color: Palette.onAccent,
    fontSize: FontSize.h2,
    fontWeight: FontWeight.bold,
  },
  primaryHint: {
    color: Palette.muted,
    fontSize: FontSize.caption,
    fontWeight: FontWeight.medium,
    lineHeight: LineHeight.caption,
    textAlign: 'center',
    marginTop: -Spacing.sm,
  },
  primaryHintStrong: {
    color: Palette.accentDeep,
    fontWeight: FontWeight.bold,
  },

  // --- Hai ô nhỏ ---
  tileRow: {
    flexDirection: 'row',
    gap: Spacing.gutter,
  },
  tile: {
    flex: 1,
    backgroundColor: Palette.surface,
    borderRadius: Radius.card,
    paddingVertical: Spacing.lg,
    paddingHorizontal: Spacing.md,
    alignItems: 'center',
    gap: Spacing.sm,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: Palette.border,
    ...Shadow.card,
  },
  tileSoon: {
    backgroundColor: Palette.surfaceAlt,
  },
  tileIcon: {
    width: 40,
    height: 40,
    borderRadius: Radius.pill,
    alignItems: 'center',
    justifyContent: 'center',
  },
  tileTitle: {
    color: Palette.text,
    fontSize: FontSize.small,
    fontWeight: FontWeight.bold,
    textAlign: 'center',
  },
  tileStatus: {
    fontSize: FontSize.tiny,
    fontWeight: FontWeight.semibold,
    textAlign: 'center',
  },

  // --- Nút quảng cáo ---
  adButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: Spacing.sm,
    height: 48,
    borderRadius: Radius.pill,
    backgroundColor: Palette.coinDim,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: Palette.coinBorder,
  },
  adButtonOff: {
    backgroundColor: Palette.surfaceAlt,
    borderColor: Palette.border,
  },
  adButtonText: {
    color: Palette.coinDeep,
    fontSize: FontSize.body,
    fontWeight: FontWeight.bold,
  },
  adHint: {
    color: Palette.faint,
    fontSize: FontSize.tiny,
    fontWeight: FontWeight.medium,
    textAlign: 'center',
    marginTop: -Spacing.md,
  },

  // --- Lịch tuần ---
  weekRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: Spacing.xs,
  },
  dayCell: {
    flex: 1,
    alignItems: 'center',
    gap: Spacing.xs,
  },
  dayLabel: {
    color: Palette.muted,
    fontSize: FontSize.tiny,
    fontWeight: FontWeight.semibold,
  },
  dayLabelToday: {
    color: Palette.accentDeep,
    fontWeight: FontWeight.bold,
  },
  dayCircle: {
    width: 34,
    height: 34,
    borderRadius: Radius.pill,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: Palette.surfaceAlt,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: Palette.border,
  },
  dayCircleWeekend: {
    backgroundColor: Palette.coinDim,
    borderColor: Palette.coinBorder,
  },
  dayCircleChecked: {
    backgroundColor: Palette.accentDeep,
    borderColor: Palette.accentDeep,
  },
  dayCircleToday: {
    borderWidth: 2,
    borderColor: Palette.accentDeep,
    backgroundColor: Palette.accentDim,
  },
  dayCoins: {
    color: Palette.faint,
    fontSize: FontSize.tiny,
    fontWeight: FontWeight.bold,
  },
  dayCoinsLabel: {
    color: Palette.faint,
    fontSize: 10,
    fontWeight: FontWeight.semibold,
  },
  weekHint: {
    color: Palette.faint,
    fontSize: FontSize.tiny,
    fontWeight: FontWeight.medium,
    lineHeight: LineHeight.caption,
    textAlign: 'center',
    marginTop: -Spacing.sm,
  },

  // --- Chung ---
  pressed: {
    opacity: Layout.pressedOpacity,
  },
  disabled: {
    opacity: 0.5,
  },
});
