package com.example

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.example.ui.theme.CyanAccent
import com.example.ui.theme.PinkAccent
import com.example.ui.theme.PurpleAccent
import androidx.lifecycle.compose.collectAsStateWithLifecycle

@Composable
fun CalendarScreen(viewModel: NovaViewModel) {
    val activities by viewModel.activities.collectAsStateWithLifecycle()
    var selectedDay by remember { mutableStateOf<Int?>(null) }
    
    val daysInMonth = 31 // Mock for May 2026
    val firstDayOffset = 4 // Friday
    
    Column(modifier = Modifier.fillMaxSize().padding(16.dp)) {
        Text("Calendario Académico", fontSize = 24.sp, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.onBackground)
        Text("Mayo 2026", color = MaterialTheme.colorScheme.onSurfaceVariant, fontSize = 14.sp)
        Spacer(modifier = Modifier.height(24.dp))
        
        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceAround) {
            listOf("LUN", "MAR", "MIÉ", "JUE", "VIE", "SÁB", "DOM").forEach {
                Text(it, color = MaterialTheme.colorScheme.onSurfaceVariant, fontSize = 12.sp, fontWeight = FontWeight.Bold)
            }
        }
        Spacer(modifier = Modifier.height(8.dp))
        
        Card(
            modifier = Modifier.fillMaxWidth().height(400.dp),
            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
            shape = RoundedCornerShape(16.dp)
        ) {
            LazyVerticalGrid(
                columns = GridCells.Fixed(7),
                contentPadding = PaddingValues(8.dp),
                modifier = Modifier.fillMaxSize()
            ) {
                items(firstDayOffset) {
                    Box(modifier = Modifier.aspectRatio(1f))
                }
                items(daysInMonth) { index ->
                    val day = index + 1
                    val dayStr = if (day < 10) "0$day" else "$day"
                    val todaysActivities = activities.filter { it.date == "2026-05-$dayStr" }
                    
                    Box(
                        modifier = Modifier
                            .aspectRatio(1f)
                            .padding(4.dp)
                            .clip(RoundedCornerShape(8.dp))
                            .background(if (selectedDay == day) MaterialTheme.colorScheme.surfaceVariant else Color.Transparent)
                            .border(1.dp, if (selectedDay == day) CyanAccent else MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.5f), RoundedCornerShape(8.dp))
                            .clickable { selectedDay = day }
                            .padding(4.dp),
                        contentAlignment = Alignment.TopEnd
                    ) {
                        Text("$day", color = MaterialTheme.colorScheme.onSurface, fontSize = 12.sp)
                        Column(modifier = Modifier.align(Alignment.BottomStart)) {
                            todaysActivities.forEach { act ->
                                Box(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .padding(top = 2.dp)
                                        .background(if(act.type == "EVALUACIÓN") PinkAccent else PurpleAccent, RoundedCornerShape(2.dp))
                                        .padding(2.dp)
                                ) {
                                    Text(act.title.take(7) + "..", color = Color.White, fontSize = 6.sp, maxLines = 1)
                                }
                            }
                        }
                    }
                }
            }
        }
        
        Spacer(modifier = Modifier.height(16.dp))
        if (selectedDay != null) {
            val dayStr = if (selectedDay!! < 10) "0$selectedDay" else "$selectedDay"
            val todaysActivities = activities.filter { it.date == "2026-05-$dayStr" }
            Text("Eventos para el $selectedDay de Mayo", fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.onBackground)
            Spacer(modifier = Modifier.height(8.dp))
            todaysActivities.forEach { act ->
                Row(modifier = Modifier.fillMaxWidth().padding(vertical = 4.dp), verticalAlignment = Alignment.CenterVertically) {
                    Box(modifier = Modifier.size(12.dp).clip(CircleShape).background(if(act.type == "EVALUACIÓN") PinkAccent else PurpleAccent))
                    Spacer(modifier = Modifier.width(8.dp))
                    Text(act.title, color = MaterialTheme.colorScheme.onSurface)
                }
            }
            if (todaysActivities.isEmpty()) {
                Text("No hay eventos programados.", color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        }
    }
}
