<template>
  <HomeScreenBlock>
    <template #header>
      Recently Played
      <ViewAllRecentSongsButton v-if="songs.length" class="float-right" />
    </template>

    <ol v-if="loading" class="space-y-3">
      <li v-for="i in 3" :key="i">
        <SongCardSkeleton />
      </li>
    </ol>
    <template v-else>
      <ol v-if="songs.length" class="space-y-3">
        <li v-for="song in songs" :key="song.id">
          <SongCard :playable="song" />
        </li>
      </ol>
      <p v-else class="text-k-text-secondary">No songs played as of late.</p>
    </template>
  </HomeScreenBlock>
</template>

<script lang="ts" setup>
import { toRef, toRefs } from 'vue'
import { overviewStore } from '@/stores/overviewStore'

import SongCard from '@/components/song/SongCard.vue'
import SongCardSkeleton from '@/components/ui/skeletons/SongCardSkeleton.vue'
import HomeScreenBlock from '@/components/screens/home/HomeScreenBlock.vue'
import ViewAllRecentSongsButton from '@/components/screens/home/ViewAllRecentSongsButton.vue'

const props = withDefaults(defineProps<{ loading?: boolean }>(), { loading: false })
const { loading } = toRefs(props)

const songs = toRef(overviewStore.state, 'recentlyPlayed')
</script>
